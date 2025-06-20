<?php

namespace Hyperlab\Dimona\Jobs;

use Hyperlab\Dimona\Actions\DimonaPeriod\CreateDimonaPeriod;
use Hyperlab\Dimona\DimonaDeclarable;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Exceptions\TooManyDimonaPeriodsCreated;
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

class DeclareDimona implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    private const MAX_DIMONA_PERIODS = 5;

    private DimonaApiClient $apiClient;

    private DimonaPayloadBuilder $payloadBuilder;

    public function __construct(
        public DimonaDeclarable $dimonaDeclarable,
        public ?string $clientId = null,
    ) {
        $this->apiClient = DimonaApiClient::new($this->clientId);
        $this->payloadBuilder = DimonaPayloadBuilder::new();
    }

    public function uniqueId(): string
    {
        return $this->dimonaDeclarable->id;
    }

    public function handle(): void
    {
        $dimonaPeriod = $this->dimonaDeclarable->dimona_periods()->latest()->first();

        match (true) {
            $this->dimonaPeriodShouldBeSynced($dimonaPeriod) => $this->syncDimonaPeriod($dimonaPeriod),
            $this->dimonaPeriodShouldBeCreated($dimonaPeriod) => $this->createDimonaPeriod(),
            $this->dimonaPeriodShouldBeUpdated($dimonaPeriod) => $this->updateDimonaPeriod($dimonaPeriod),
            $this->dimonaPeriodShouldBeCancelled($dimonaPeriod) => $this->cancelDimonaPeriod($dimonaPeriod),
            default => null,
        };
    }

    private function dimonaPeriodShouldBeSynced(?DimonaPeriod $dimonaPeriod): bool
    {
        if ($dimonaPeriod === null) {
            return false;
        }

        return $dimonaPeriod->state === DimonaPeriodState::Pending;
    }

    private function dimonaPeriodShouldBeCreated(?DimonaPeriod $dimonaPeriod): bool
    {
        if ($this->dimonaDeclarable->dimona_periods()->count() >= self::MAX_DIMONA_PERIODS) {
            throw new TooManyDimonaPeriodsCreated;
        }

        if (! $this->dimonaDeclarable->shouldDeclareDimona()) {
            return false;
        }

        if ($dimonaPeriod === null) {
            return true;
        }

        if ($dimonaPeriod->state === DimonaPeriodState::Cancelled) {
            return true;
        }

        return false;
    }

    private function dimonaPeriodShouldBeUpdated(?DimonaPeriod $dimonaPeriod): bool
    {
        // TODO: Implement update logic
        return false;
    }

    private function dimonaPeriodShouldBeCancelled(?DimonaPeriod $dimonaPeriod): bool
    {
        if ($dimonaPeriod === null) {
            return false;
        }

        if (! in_array($dimonaPeriod->state, [DimonaPeriodState::Accepted, DimonaPeriodState::AcceptedWithWarning])) {
            return false;
        }

        if ($dimonaPeriod->state === DimonaPeriodState::AcceptedWithWarning) {
            return true;
        }

        if (! $this->dimonaDeclarable->shouldDeclareDimona()) {
            return true;
        }

        return false;
    }

    private function syncDimonaPeriod(DimonaPeriod $dimonaPeriod): void
    {
        $dimonaDeclaration = $dimonaPeriod->dimona_declarations()->latest()->first();

        SyncDimonaDeclaration::dispatch($this->dimonaDeclarable, $dimonaDeclaration, $this->clientId);
    }

    private function createDimonaPeriod(): void
    {
        $data = $this->dimonaDeclarable->getDimonaData();

        $data->workerType = WorkerTypeExceptionService::new()->resolveWorkerType($data);

        $dimonaPeriod = CreateDimonaPeriod::new()->execute($this->dimonaDeclarable, $data->workerType);

        $payload = $this->payloadBuilder->buildCreatePayload($data);

        $dimonaDeclaration = $this->createDimonaDeclaration($dimonaPeriod, DimonaDeclarationType::In, $payload);

        if ($dimonaDeclaration->state === DimonaDeclarationState::Pending) {
            SyncDimonaDeclaration::dispatch($this->dimonaDeclarable, $dimonaDeclaration, $this->clientId)->delay(5);
        } else {
            self::dispatch($this->dimonaDeclarable, $this->clientId);
        }
    }

    private function updateDimonaPeriod(DimonaPeriod $dimonaPeriod): void
    {
        $data = $this->dimonaDeclarable->getDimonaData();

        $payload = $this->payloadBuilder->buildUpdatePayload($dimonaPeriod, $data);

        $dimonaDeclaration = $this->createDimonaDeclaration($dimonaPeriod, DimonaDeclarationType::Update, $payload);

        if ($dimonaDeclaration->state === DimonaDeclarationState::Pending) {
            SyncDimonaDeclaration::dispatch($this->dimonaDeclarable, $dimonaDeclaration, $this->clientId)->delay(5);
        } else {
            self::dispatch($this->dimonaDeclarable, $this->clientId);
        }
    }

    private function cancelDimonaPeriod(DimonaPeriod $dimonaPeriod): void
    {
        $data = $this->dimonaDeclarable->getDimonaData();

        $payload = $this->payloadBuilder->buildCancelPayload($dimonaPeriod, $data);

        $dimonaDeclaration = $this->createDimonaDeclaration($dimonaPeriod, DimonaDeclarationType::Cancel, $payload);

        if ($dimonaDeclaration->state === DimonaDeclarationState::Pending) {
            SyncDimonaDeclaration::dispatch($this->dimonaDeclarable, $dimonaDeclaration, $this->clientId)->delay(5);
        } else {
            self::dispatch($this->dimonaDeclarable, $this->clientId);
        }
    }

    private function createDimonaDeclaration(
        DimonaPeriod $dimonaPeriod, DimonaDeclarationType $type, array $payload
    ): DimonaDeclaration {
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
        } finally {
            $dimonaPeriod->updateState();
        }

        return $dimonaDeclaration;
    }
}
