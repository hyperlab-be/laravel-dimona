<?php

namespace Hyperlab\Dimona\Jobs;

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeDimonaPeriodOperations;
use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeDimonaPeriods;
use Hyperlab\Dimona\Data\DimonaPeriodOperationData;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodOperation;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Exceptions\DimonaDeclarationIsNotYetProcessed;
use Hyperlab\Dimona\Exceptions\DimonaServiceIsDown;
use Hyperlab\Dimona\Exceptions\InvalidDimonaApiRequest;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Services\DimonaApiClient;
use Hyperlab\Dimona\Services\DimonaPayloadBuilder;
use Hyperlab\Dimona\Services\WorkerTypeExceptionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncDimonaPeriods implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private DimonaApiClient $apiClient;

    private DimonaPayloadBuilder $payloadBuilder;

    private WorkerTypeExceptionService $workerTypeExceptionService;

    /**
     * @param  Collection<EmploymentData>  $employments
     */
    public function __construct(
        public string $employerEnterpriseNumber,
        public string $workerSocialSecurityNumber,
        public CarbonPeriodImmutable $period,
        public Collection $employments,
        public ?string $clientId = null,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->employerEnterpriseNumber}-{$this->workerSocialSecurityNumber}";
    }

    public function handle(): void
    {
        $this->apiClient = DimonaApiClient::new($this->clientId);
        $this->payloadBuilder = DimonaPayloadBuilder::new();
        $this->workerTypeExceptionService = WorkerTypeExceptionService::new();

        // Step 1: Sync all pending declarations

        $hasPendingDeclarations = $this->syncPendingDeclarations();

        if ($hasPendingDeclarations) {
            $this->redispatchJob();

            return;
        }

        // Step 2: Determine and apply period operations

        $actualDimonaPeriods = DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->whereBetween('starts_at', [$this->period->start, $this->period->end])
            ->whereNotIn('state', [DimonaPeriodState::Cancelled, DimonaPeriodState::Failed])
            ->orderBy('starts_at')
            ->get();

        $expectedDimonaPeriods = ComputeDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: $this->employments
        );

        $dimonaPeriodOperations = ComputeDimonaPeriodOperations::new()->execute($expectedDimonaPeriods, $actualDimonaPeriods);

        // Separate cancel operations from other operations
        $cancelOperations = $dimonaPeriodOperations->filter(fn ($op) => $op->type === DimonaPeriodOperation::Cancel);
        $otherOperations = $dimonaPeriodOperations->filter(fn ($op) => $op->type !== DimonaPeriodOperation::Cancel);

        // If there are cancel operations, execute only those and redispatch
        if ($cancelOperations->isNotEmpty()) {
            $cancelOperations->each(fn ($operation) => $this->handleCancel($operation));
            $this->redispatchJob();

            return;
        }

        // Otherwise, execute update and create operations
        $otherOperations->each(function (DimonaPeriodOperationData $operation) {
            match ($operation->type) {
                DimonaPeriodOperation::Create => $this->handleCreate($operation),
                DimonaPeriodOperation::Update => $this->handleUpdate($operation),
                DimonaPeriodOperation::Cancel => null, // Already handled above
            };
        });

        // Step 3: Re-dispatch if operations were applied

        if ($otherOperations->isNotEmpty()) {
            $this->redispatchJob();
        }
    }

    /**
     * Sync all pending declarations.
     * Returns true if any declarations are still pending after sync.
     */
    private function syncPendingDeclarations(): bool
    {
        return DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->whereBetween('starts_at', [$this->period->start, $this->period->end])
            ->whereIn('state', [DimonaPeriodState::Pending, DimonaPeriodState::Waiting])
            ->orderBy('starts_at')
            ->with([
                'dimona_declarations' => function ($query) {
                    $query
                        ->whereIn('state', [DimonaDeclarationState::Pending, DimonaDeclarationState::Waiting])
                        ->latest()
                        ->limit(1);
                },
            ])
            ->get()
            ->reduce(
                function (bool $hasPendingDeclarations, DimonaPeriod $period) {
                    /** @var DimonaDeclaration $pendingDeclaration */
                    $pendingDeclaration = $period->dimona_declarations->first();

                    $this->syncDeclaration($pendingDeclaration);

                    // Check if still pending or waiting after sync
                    if (in_array($pendingDeclaration->state, [DimonaDeclarationState::Pending, DimonaDeclarationState::Waiting])) {
                        $hasPendingDeclarations = true;
                    }

                    return $hasPendingDeclarations;
                },
                false
            );
    }

    /**
     * Sync a single declaration with the API.
     */
    private function syncDeclaration(DimonaDeclaration $declaration): void
    {
        try {
            $response = $this->apiClient->getDeclaration($declaration->reference);

            DB::transaction(function () use ($declaration, $response) {
                if ($declaration->dimona_period->reference === null) {
                    $declaration->dimona_period->updateReference($response['reference']);
                }

                $declaration->updateState(
                    state: match ($response['result']) {
                        'A' => DimonaDeclarationState::Accepted,
                        'W' => DimonaDeclarationState::AcceptedWithWarning,
                        'B' => DimonaDeclarationState::Refused,
                        'S' => DimonaDeclarationState::Waiting,
                        default => DimonaDeclarationState::Failed,
                    },
                    anomalies: $response['anomalies']
                );

                $declaration->refresh();

                // Handle worker type exceptions based on anomalies (may create cancel declarations)
                $this->workerTypeExceptionService->handleExceptions($declaration);

                // Update period state after handling exceptions
                $declaration->dimona_period->updateState();
            });
        } catch (DimonaDeclarationIsNotYetProcessed|DimonaServiceIsDown) {
            // Declaration not yet processed or service is down, keep it pending
            // Will be retried on next job dispatch
        } catch (InvalidDimonaApiRequest $exception) {
            DB::transaction(function () use ($declaration, $exception) {
                $declaration->updateState(
                    state: DimonaDeclarationState::Failed,
                    anomalies: $exception->getPrevious()->response->json(),
                );

                $declaration->dimona_period->updateState();
            });
        } catch (RequestException $exception) {
            DB::transaction(function () use ($declaration, $exception) {
                $declaration->updateState(
                    state: DimonaDeclarationState::Failed,
                    anomalies: $exception->response->json(),
                );

                $declaration->dimona_period->updateState();
            });
        }

        $declaration->refresh();
    }

    private function handleCreate(DimonaPeriodOperationData $operation): void
    {
        $payload = $this->payloadBuilder->buildCreatePayload(
            dimonaPeriodData: $operation->expected
        );

        $dimonaPeriod = DimonaPeriod::query()->create([
            'employer_enterprise_number' => $this->employerEnterpriseNumber,
            'worker_social_security_number' => $this->workerSocialSecurityNumber,
            'worker_type' => $operation->expected->workerType,
            'joint_commission_number' => $operation->expected->jointCommissionNumber,
            'starts_at' => $operation->expected->startsAt,
            'ends_at' => $operation->expected->endsAt,
            'state' => DimonaPeriodState::New,
        ]);

        // Sync employment IDs via pivot table
        foreach ($operation->expected->employmentIds as $employmentId) {
            DB::table('dimona_period_employment')->insert([
                'dimona_period_id' => $dimonaPeriod->id,
                'employment_id' => $employmentId,
            ]);
        }

        $this->createDimonaDeclaration(
            dimonaPeriod: $dimonaPeriod,
            type: DimonaDeclarationType::In,
            payload: $payload
        );
    }

    private function handleUpdate(DimonaPeriodOperationData $operation): void
    {
        $payload = $this->payloadBuilder->buildUpdatePayload(
            dimonaPeriod: $operation->actual,
            dimonaPeriodData: $operation->expected
        );

        $operation->actual->update([
            'starts_at' => $operation->expected->startsAt,
            'ends_at' => $operation->expected->endsAt,
        ]);

        // Sync employment IDs via pivot table
        \DB::table('dimona_period_employment')
            ->where('dimona_period_id', $operation->actual->id)
            ->delete();

        foreach ($operation->expected->employmentIds as $employmentId) {
            \DB::table('dimona_period_employment')->insert([
                'dimona_period_id' => $operation->actual->id,
                'employment_id' => $employmentId,
            ]);
        }

        $this->createDimonaDeclaration(
            dimonaPeriod: $operation->actual,
            type: DimonaDeclarationType::Update,
            payload: $payload
        );
    }

    private function handleCancel(DimonaPeriodOperationData $operation): void
    {
        $payload = $this->payloadBuilder->buildCancelPayload(
            dimonaPeriod: $operation->actual
        );

        $this->createDimonaDeclaration(
            dimonaPeriod: $operation->actual,
            type: DimonaDeclarationType::Cancel,
            payload: $payload
        );
    }

    private function createDimonaDeclaration(
        DimonaPeriod $dimonaPeriod, DimonaDeclarationType $type, array $payload
    ): void {
        $dimonaDeclaration = $dimonaPeriod->createDimonaDeclaration(
            type: $type,
            payload: $payload
        );

        try {
            $result = $this->apiClient->createDeclaration($payload);
            $dimonaDeclaration->updateReference($result['reference']);
        } catch (RequestException $exception) {
            $dimonaDeclaration->updateState(
                state: DimonaDeclarationState::Failed,
                anomalies: $exception->response->json(),
            );
        }

        $dimonaPeriod->updateState();
    }

    private function redispatchJob(): void
    {
        $delay = $this->calculateBackoff();

        self::dispatch(
            $this->employerEnterpriseNumber,
            $this->workerSocialSecurityNumber,
            $this->period,
            $this->employments,
            $this->clientId
        )->delay($delay);
    }

    /**
     * Calculate backoff delay based on the oldest pending declaration.
     * Uses exponential backoff: 1s -> 60s -> 3600s.
     */
    private function calculateBackoff(): int
    {
        $oldestPendingDeclaration = DimonaDeclaration::query()
            ->whereHas('dimona_period', function ($query) {
                $query
                    ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
                    ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
                    ->whereBetween('starts_at', [$this->period->start, $this->period->end]);
            })
            ->where('state', DimonaDeclarationState::Pending)
            ->oldest()
            ->first();

        if (! $oldestPendingDeclaration) {
            return 5; // Default delay for new operations
        }

        $runtimeInSeconds = $oldestPendingDeclaration->created_at->diffInSeconds(Carbon::now(), true);

        return match (true) {
            $runtimeInSeconds <= 30 => 1,
            $runtimeInSeconds <= 1200 => 60,
            default => 3600,
        };
    }
}
