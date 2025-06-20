<?php

namespace Hyperlab\Dimona\Services;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\DimonaData;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaWorkerTypeException;

class WorkerTypeExceptionService
{
    public static function new(): static
    {
        return app(static::class);
    }

    /**
     * Resolve the correct worker type based on exceptions
     */
    public function resolveWorkerType(DimonaData $data): WorkerType
    {
        if ($data->workerType === WorkerType::Flexi || $data->workerType === WorkerType::Student) {
            $exceptionExists = DimonaWorkerTypeException::query()
                ->where('social_security_number', $data->workerSocialSecurityNumber)
                ->where('starts_at', '<=', $data->startsAt)
                ->where('ends_at', '>=', $data->startsAt)
                ->where('worker_type', $data->workerType)
                ->exists();

            return $exceptionExists ? WorkerType::Other : $data->workerType;
        }

        return $data->workerType;
    }

    /**
     * Handle worker type exceptions based on declaration anomalies
     */
    public function handleExceptions(DimonaDeclaration $declaration, DimonaData $data): void
    {
        if ($declaration->anomalies()->flexiRequirementsAreNotMet()) {
            $this->createFlexiException($data->workerSocialSecurityNumber, $data->startsAt);
        }

        if ($declaration->anomalies()->studentRequirementsAreNotMet()) {
            $this->createStudentException($data->workerSocialSecurityNumber, $data->startsAt);
        }
    }

    /**
     * Create a flexi worker type exception
     */
    private function createFlexiException(string $socialSecurityNumber, CarbonImmutable $startsAt): DimonaWorkerTypeException
    {
        return DimonaWorkerTypeException::query()->create([
            'social_security_number' => $socialSecurityNumber,
            'worker_type' => WorkerType::Flexi,
            'starts_at' => $startsAt->startOfQuarter(),
            'ends_at' => $startsAt->endOfQuarter(),
        ]);
    }

    /**
     * Create a student worker type exception
     */
    private function createStudentException(string $socialSecurityNumber, CarbonImmutable $startsAt): DimonaWorkerTypeException
    {
        return DimonaWorkerTypeException::query()->create([
            'social_security_number' => $socialSecurityNumber,
            'worker_type' => WorkerType::Student,
            'starts_at' => $startsAt->startOfYear(),
            'ends_at' => $startsAt->endOfYear(),
        ]);
    }
}
