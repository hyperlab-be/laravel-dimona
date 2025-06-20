<?php

namespace Hyperlab\Dimona\Jobs;

use Hyperlab\Dimona\DimonaDeclarable;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Exceptions\DimonaDeclarationIsNotYetProcessed;
use Hyperlab\Dimona\Exceptions\DimonaServiceIsDown;
use Hyperlab\Dimona\Facades\Dimona;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Services\DimonaApiClient;
use Hyperlab\Dimona\Services\WorkerTypeExceptionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SyncDimonaDeclaration implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    private DimonaApiClient $apiClient;

    private WorkerTypeExceptionService $workerTypeExceptionService;

    public function __construct(
        public DimonaDeclarable $dimonaDeclarable,
        public DimonaDeclaration $dimonaDeclaration,
        public ?string $clientId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->dimonaDeclaration->id;
    }

    public function handle(): void
    {
        $this->apiClient = DimonaApiClient::new($this->clientId);
        $this->workerTypeExceptionService = WorkerTypeExceptionService::new();

        try {
            $this->syncDimonaDeclaration();
        } catch (DimonaDeclarationIsNotYetProcessed|DimonaServiceIsDown) {
            $this->release($this->calculateBackoff());

            return;
        }

        $this->handleWorkerTypeExceptions();

        Dimona::client($this->clientId)->declare($this->dimonaDeclarable);
    }

    private function syncDimonaDeclaration(): void
    {
        try {
            $response = $this->apiClient->getDeclaration($this->dimonaDeclaration->reference);

            if ($this->dimonaDeclaration->dimona_period->reference === null) {
                $this->dimonaDeclaration->dimona_period->updateReference(
                    reference: $response['reference']
                );
            }

            $this->dimonaDeclaration->updateState(
                state: match ($response['result']) {
                    'A' => DimonaDeclarationState::Accepted,
                    'W' => DimonaDeclarationState::AcceptedWithWarning,
                    'B' => DimonaDeclarationState::Refused,
                    'S' => DimonaDeclarationState::Waiting,
                    default => DimonaDeclarationState::Failed,
                },
                anomalies: $response['anomalies']
            );
        } catch (DimonaDeclarationIsNotYetProcessed|DimonaServiceIsDown $exception) {
            throw $exception;
        } catch (RequestException $exception) {
            $this->dimonaDeclaration->updateState(
                state: DimonaDeclarationState::Failed,
                anomalies: $exception->response->json(),
            );
        }

        $this->dimonaDeclaration->dimona_period->updateState();
    }

    private function handleWorkerTypeExceptions(): void
    {
        $data = $this->dimonaDeclarable->getDimonaData();

        $this->workerTypeExceptionService->handleExceptions($this->dimonaDeclaration, $data);
    }

    public function calculateBackoff(): int
    {
        $runtimeInSeconds = $this->dimonaDeclaration->created_at->diffInSeconds(Carbon::now(), true);

        return match (true) {
            $runtimeInSeconds <= 30 => 1,
            $runtimeInSeconds <= 1200 => 60,
            default => 3600,
        };
    }
}
