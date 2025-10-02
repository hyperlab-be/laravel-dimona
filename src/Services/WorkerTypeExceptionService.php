<?php

namespace Hyperlab\Dimona\Services;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
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
    public function resolveWorkerType(
        string $workerSocialSecurityNumber, WorkerType $workerType, CarbonImmutable $employmentStartsAt
    ): WorkerType {
        if ($workerType === WorkerType::Flexi || $workerType === WorkerType::Student) {
            $exceptionExists = DimonaWorkerTypeException::query()
                ->where('social_security_number', $workerSocialSecurityNumber)
                ->where('starts_at', '<=', $employmentStartsAt)
                ->where('ends_at', '>=', $employmentStartsAt)
                ->where('worker_type', $workerType)
                ->exists();

            return $exceptionExists ? WorkerType::Other : $workerType;
        }

        return $workerType;
    }

    /**
     * Handle worker type exceptions based on declaration anomalies
     */
    public function handleExceptions(DimonaDeclaration $declaration): void
    {
        if ($declaration->anomalies()->flexiRequirementsAreNotMet()) {
            $this->createFlexiException($declaration);
        }

        if ($declaration->anomalies()->studentRequirementsAreNotMet()) {
            $this->createStudentException($declaration);
        }
    }

    /**
     * Create a flexi worker type exception
     */
    private function createFlexiException(DimonaDeclaration $declaration): DimonaWorkerTypeException
    {
        /** @var DimonaPeriod $dimonaPeriod */
        $dimonaPeriod = $declaration->dimona_period;

        return DimonaWorkerTypeException::query()->create([
            'social_security_number' => $dimonaPeriod->worker_social_security_number,
            'worker_type' => WorkerType::Flexi,
            'starts_at' => $dimonaPeriod->starts_at->startOfQuarter(),
            'ends_at' => $dimonaPeriod->starts_at->endOfQuarter(),
        ]);
    }

    /**
     * Create a student worker type exception
     */
    private function createStudentException(DimonaDeclaration $declaration): DimonaWorkerTypeException
    {
        /** @var DimonaPeriod $dimonaPeriod */
        $dimonaPeriod = $declaration->dimona_period;

        return DimonaWorkerTypeException::query()->create([
            'social_security_number' => $dimonaPeriod->worker_social_security_number,
            'worker_type' => WorkerType::Student,
            'starts_at' => $dimonaPeriod->starts_at->startOfYear(),
            'ends_at' => $dimonaPeriod->starts_at->endOfYear(),
        ]);
    }
}
