<?php

namespace Hyperlab\Dimona\Jobs;

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Hyperlab\Dimona\Actions\DimonaPeriod\DetachDeletedEmploymentsFromDimonaPeriods;
use Hyperlab\Dimona\Actions\DimonaPeriod\SyncDimonaPeriodsWithExpectations;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Services\DimonaService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SyncDimonaPeriodsJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private DimonaService $dimonaService;

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

    public function handle($i = 1): void
    {
        $this->dimonaService = new DimonaService($this->clientId);

        // Step 1: Sync all pending declarations

        if ($this->syncDimonaPeriods()) {
            $this->redispatchJob();

            return;
        }

        // Step 2: Remove the deleted employment IDs from actual dimona periods

        DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            period: $this->period,
            employments: $this->employments,
        );

        // Step 3: Compute expected dimona periods

        $expectedDimonaPeriods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: $this->employments
        );

        // Step 4: Link expected dimona periods to actual dimona periods

        SyncDimonaPeriodsWithExpectations::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            expectedDimonaPeriods: $expectedDimonaPeriods
        );

        // Step 5: Cancel actual dimona periods (without employment ids | with state accepted with warning)

        if ($this->cancelDimonaPeriods()) {
            $this->redispatchJob();

            return;
        }

        if ($this->updateDimonaPeriods()) {
            $this->redispatchJob();

            return;
        }

        if ($this->createDimonaPeriods()) {
            $this->redispatchJob();
        }
    }

    /**
     * Create dimona periods (with state new)
     */
    private function createDimonaPeriods(): bool
    {
        return DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->whereBetween('start_date', [$this->period->start->format('Y-m-d'), $this->period->end->format('Y-m-d')])
            ->where('state', DimonaPeriodState::New)
            ->get()
            ->each(fn (DimonaPeriod $dimonaPeriod) => $this->dimonaService->createDimonaPeriod($dimonaPeriod))
            ->isNotEmpty();
    }

    private function updateDimonaPeriods(): bool
    {
        return DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->whereBetween('start_date', [$this->period->start->format('Y-m-d'), $this->period->end->format('Y-m-d')])
            ->where('state', DimonaPeriodState::Outdated)
            ->get()
            ->each(fn (DimonaPeriod $dimonaPeriod) => $this->dimonaService->updateDimonaPeriod($dimonaPeriod))
            ->isNotEmpty();
    }

    /**
     * Cancel dimona periods (without employment ids | with state accepted with warning)
     */
    private function cancelDimonaPeriods(): bool
    {
        return DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->whereBetween('start_date', [$this->period->start->format('Y-m-d'), $this->period->end->format('Y-m-d')])
            ->where(function ($query) {
                $query
                    ->where(function ($query) {
                        $query
                            ->whereIn('state', [DimonaPeriodState::Accepted, DimonaPeriodState::Waiting])
                            ->whereDoesntHave('dimona_period_employments');
                    })
                    ->orWhere('state', DimonaPeriodState::AcceptedWithWarning);
            })
            ->get()
            ->each(fn (DimonaPeriod $dimonaPeriod) => $this->dimonaService->cancelDimonaPeriod($dimonaPeriod))
            ->isNotEmpty();
    }

    private function syncDimonaPeriods(): bool
    {
        return DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->whereBetween('start_date', [$this->period->start->format('Y-m-d'), $this->period->end->format('Y-m-d')])
            ->whereIn('state', [DimonaPeriodState::Pending, DimonaPeriodState::Waiting])
            ->orderBy('start_date')
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
                function (bool $hasPendingDeclarations, DimonaPeriod $dimonaPeriod) {
                    $dimonaPeriodIsStillPending = $this->dimonaService->syncDimonaPeriod($dimonaPeriod);

                    if ($dimonaPeriodIsStillPending) {
                        $hasPendingDeclarations = true;
                    }

                    return $hasPendingDeclarations;
                },
                false
            );
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
                    ->whereBetween('start_date', [$this->period->start->format('Y-m-d'), $this->period->end->format('Y-m-d')]);
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
