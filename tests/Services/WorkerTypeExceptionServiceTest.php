<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Models\DimonaWorkerTypeException;
use Hyperlab\Dimona\Services\WorkerTypeExceptionService;

describe('resolve worker type', function () {

    describe('with exceptions', function () {

        it('resolves worker type to Other when exception exists for Flexi worker', function () {
            // Create an exception in the database
            DimonaWorkerTypeException::query()->create([
                'social_security_number' => '12345678901',
                'worker_type' => WorkerType::Flexi,
                'starts_at' => CarbonImmutable::parse('2023-01-01'),
                'ends_at' => CarbonImmutable::parse('2023-03-31'),
            ]);

            // Act
            $resolvedType = WorkerTypeExceptionService::new()->resolveWorkerType(
                workerSocialSecurityNumber: '12345678901',
                workerType: WorkerType::Flexi,
                employmentStartsAt: CarbonImmutable::parse('2023-01-15 10:00:00')
            );

            // Assert
            expect($resolvedType)->toBe(WorkerType::Other);
        });

        it('resolves worker type to Other when exception exists for Student worker', function () {
            // Create an exception in the database
            DimonaWorkerTypeException::query()->create([
                'social_security_number' => '12345678901',
                'worker_type' => WorkerType::Student,
                'starts_at' => CarbonImmutable::parse('2023-01-01'),
                'ends_at' => CarbonImmutable::parse('2023-12-31'),
            ]);

            // Act
            $resolvedType = WorkerTypeExceptionService::new()->resolveWorkerType(
                workerSocialSecurityNumber: '12345678901',
                workerType: WorkerType::Student,
                employmentStartsAt: CarbonImmutable::parse('2023-01-15 10:00:00')
            );

            // Assert
            expect($resolvedType)->toBe(WorkerType::Other);
        });

    });

    describe('without exceptions', function () {

        it('keeps original worker type when no exception exists for Flexi worker', function () {
            // Make sure no exception exists for this worker
            DimonaWorkerTypeException::query()->where('social_security_number', '12345678902')->delete();

            // Act
            $resolvedType = WorkerTypeExceptionService::new()->resolveWorkerType(
                workerSocialSecurityNumber: '12345678902',
                workerType: WorkerType::Flexi,
                employmentStartsAt: CarbonImmutable::parse('2023-01-15 10:00:00')
            );

            // Assert
            expect($resolvedType)->toBe(WorkerType::Flexi);
        });

        it('keeps original worker type when no exception exists for Student worker', function () {
            // Make sure no exception exists for this worker
            DimonaWorkerTypeException::query()->where('social_security_number', '12345678903')->delete();

            // Act
            $resolvedType = WorkerTypeExceptionService::new()->resolveWorkerType(
                workerSocialSecurityNumber: '12345678903',
                workerType: WorkerType::Student,
                employmentStartsAt: CarbonImmutable::parse('2023-01-15 10:00:00')
            );

            // Assert
            expect($resolvedType)->toBe(WorkerType::Student);
        });

        it('keeps original worker type when worker type is not Flexi or Student', function () {
            // Arrange
            $startsAt = CarbonImmutable::parse('2023-01-15 10:00:00');

            // Act
            $resolvedType = WorkerTypeExceptionService::new()->resolveWorkerType(
                workerSocialSecurityNumber: '12345678904',
                workerType: WorkerType::Other,
                employmentStartsAt: $startsAt
            );

            // Assert
            expect($resolvedType)->toBe(WorkerType::Other);
        });

    });
});

describe('handle exceptions', function () {

    it('creates a flexi exception when flexi requirements are not met', function () {
        $declaration = DimonaDeclaration::factory()
            ->for(
                DimonaPeriod::factory()->state([
                    'worker_social_security_number' => '12345678905',
                    'worker_type' => WorkerType::Flexi,
                    'start_date' => '2023-01-15',
                ]),
                'dimona_period'
            )
            ->create([
                'anomalies' => ['90017-510'],
            ]);

        // Act
        WorkerTypeExceptionService::new()->handleExceptions($declaration);

        // Assert
        $exception = DimonaWorkerTypeException::query()
            ->where('social_security_number', '12345678905')
            ->where('worker_type', WorkerType::Flexi)
            ->first();

        expect($exception)->not->toBeNull()
            ->and($exception->worker_type)->toBe(WorkerType::Flexi)
            ->and($exception->social_security_number)->toBe('12345678905')
            ->and($exception->starts_at->format('Y-m-d'))->toBe('2023-01-01')
            ->and($exception->ends_at->format('Y-m-d'))->toBe('2023-03-31');
    });

    it('creates a student exception when student requirements are not met', function () {
        $declaration = DimonaDeclaration::factory()
            ->for(
                DimonaPeriod::factory()->state([
                    'worker_social_security_number' => '12345678906',
                    'worker_type' => WorkerType::Student,
                    'start_date' => '2023-01-15',
                ]),
                'dimona_period'
            )
            ->create([
                'anomalies' => ['90017-369'],
            ]);

        // Act
        WorkerTypeExceptionService::new()->handleExceptions($declaration);

        // Assert
        $exception = DimonaWorkerTypeException::query()
            ->where('social_security_number', '12345678906')
            ->where('worker_type', WorkerType::Student)
            ->first();

        expect($exception)->not->toBeNull()
            ->and($exception->worker_type)->toBe(WorkerType::Student)
            ->and($exception->social_security_number)->toBe('12345678906')
            ->and($exception->starts_at->format('Y-m-d'))->toBe('2023-01-01')
            ->and($exception->ends_at->format('Y-m-d'))->toBe('2023-12-31');
    });

    it('does not create exceptions when no anomalies are present', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'anomalies' => [],
        ]);

        // Act
        WorkerTypeExceptionService::new()->handleExceptions($declaration);

        // Assert
        $exceptionCount = DimonaWorkerTypeException::query()
            ->where('social_security_number', $declaration->dimona_period->worker_social_security_number)
            ->count();

        expect($exceptionCount)->toBe(0);
    });

});
