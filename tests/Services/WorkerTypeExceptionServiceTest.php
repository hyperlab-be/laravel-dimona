<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Models\DimonaWorkerTypeException;
use Hyperlab\Dimona\Services\WorkerTypeExceptionService;

it('resolves worker type to Other when exception exists for Flexi worker', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = CarbonImmutable::parse('2023-01-15 10:00:00');
    $endsAt = CarbonImmutable::parse('2023-01-15 14:00:00');

    // Create an exception in the database
    DimonaWorkerTypeException::create([
        'social_security_number' => '12345678901',
        'worker_type' => WorkerType::Flexi,
        'starts_at' => $startsAt->copy()->startOfDay(),
        'ends_at' => $startsAt->copy()->endOfDay(),
    ]);

    // Act
    $resolvedType = $service->resolveWorkerType(
        workerSocialSecurityNumber: '12345678901',
        workerType: WorkerType::Flexi,
        employmentStartsAt: $startsAt
    );

    // Assert
    expect($resolvedType)->toBe(WorkerType::Other);
});

it('resolves worker type to Other when exception exists for Student worker', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = CarbonImmutable::parse('2023-01-15 10:00:00');
    $endsAt = CarbonImmutable::parse('2023-01-15 14:00:00');

    // Create an exception in the database
    DimonaWorkerTypeException::create([
        'social_security_number' => '12345678901',
        'worker_type' => WorkerType::Student,
        'starts_at' => $startsAt->copy()->startOfDay(),
        'ends_at' => $startsAt->copy()->endOfDay(),
    ]);

    // Act
    $resolvedType = $service->resolveWorkerType(
        workerSocialSecurityNumber: '12345678901',
        workerType: WorkerType::Student,
        employmentStartsAt: $startsAt
    );

    // Assert
    expect($resolvedType)->toBe(WorkerType::Other);
});

it('keeps original worker type when no exception exists for Flexi worker', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = CarbonImmutable::parse('2023-01-15 10:00:00');
    $endsAt = CarbonImmutable::parse('2023-01-15 14:00:00');

    // Make sure no exception exists for this worker
    DimonaWorkerTypeException::where('social_security_number', '12345678902')->delete();

    // Act
    $resolvedType = $service->resolveWorkerType(
        workerSocialSecurityNumber: '12345678902',
        workerType: WorkerType::Flexi,
        employmentStartsAt: $startsAt
    );

    // Assert
    expect($resolvedType)->toBe(WorkerType::Flexi);
});

it('keeps original worker type when no exception exists for Student worker', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = CarbonImmutable::parse('2023-01-15 10:00:00');
    $endsAt = CarbonImmutable::parse('2023-01-15 14:00:00');

    // Make sure no exception exists for this worker
    DimonaWorkerTypeException::where('social_security_number', '12345678903')->delete();

    // Act
    $resolvedType = $service->resolveWorkerType(
        workerSocialSecurityNumber: '12345678903',
        workerType: WorkerType::Student,
        employmentStartsAt: $startsAt
    );

    // Assert
    expect($resolvedType)->toBe(WorkerType::Student);
});

it('keeps original worker type when worker type is not Flexi or Student', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = CarbonImmutable::parse('2023-01-15 10:00:00');

    // Act
    $resolvedType = $service->resolveWorkerType(
        workerSocialSecurityNumber: '12345678904',
        workerType: WorkerType::Other,
        employmentStartsAt: $startsAt
    );

    // Assert
    expect($resolvedType)->toBe(WorkerType::Other);
});

it('creates a flexi exception when flexi requirements are not met', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = CarbonImmutable::parse('2023-01-15 10:00:00');

    // Create a DimonaPeriod
    $dimonaPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678905',
        'joint_commission_number' => '123',
        'worker_type' => WorkerType::Flexi,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHours(4),
        'state' => DimonaPeriodState::New,
    ]);
    $dimonaPeriod->dimona_period_employments()->create([
        'employment_id' => 'test-employment-id',
    ]);

    // Create a DimonaDeclaration with flexi anomaly
    $declaration = DimonaDeclaration::create([
        'dimona_period_id' => $dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
        'anomalies' => ['90017-510'],
    ]);

    // Act
    $service->handleExceptions($declaration);

    // Assert
    $exception = DimonaWorkerTypeException::where('social_security_number', '12345678905')
        ->where('worker_type', WorkerType::Flexi)
        ->first();

    expect($exception)->not->toBeNull()
        ->and($exception->worker_type)->toBe(WorkerType::Flexi)
        ->and($exception->social_security_number)->toBe('12345678905')
        ->and($exception->starts_at->format('Y-m-d'))->toBe('2023-01-01')
        ->and($exception->ends_at->format('Y-m-d'))->toBe('2023-03-31');
});

it('creates a student exception when student requirements are not met', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = CarbonImmutable::parse('2023-01-15 10:00:00');

    // Create a DimonaPeriod
    $dimonaPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678906',
        'joint_commission_number' => '123',
        'worker_type' => WorkerType::Student,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHours(4),
        'state' => DimonaPeriodState::New,
    ]);
    $dimonaPeriod->dimona_period_employments()->create([
        'employment_id' => 'test-employment-id',
    ]);

    // Create a DimonaDeclaration with student anomaly
    $declaration = DimonaDeclaration::create([
        'dimona_period_id' => $dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
        'anomalies' => ['90017-369'],
    ]);

    // Act
    $service->handleExceptions($declaration);

    // Assert
    $exception = DimonaWorkerTypeException::where('social_security_number', '12345678906')
        ->where('worker_type', WorkerType::Student)
        ->first();

    expect($exception)->not->toBeNull()
        ->and($exception->worker_type)->toBe(WorkerType::Student)
        ->and($exception->social_security_number)->toBe('12345678906')
        ->and($exception->starts_at->format('Y-m-d'))->toBe('2023-01-01')
        ->and($exception->ends_at->format('Y-m-d'))->toBe('2023-12-31');
});

it('does not create exceptions when no anomalies are present', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = CarbonImmutable::parse('2023-01-15 10:00:00');

    // Make sure no exception exists for this worker
    DimonaWorkerTypeException::where('social_security_number', '12345678907')->delete();

    // Create a DimonaPeriod
    $dimonaPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678907',
        'joint_commission_number' => '123',
        'worker_type' => WorkerType::Flexi,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHours(4),
        'state' => DimonaPeriodState::New,
    ]);
    $dimonaPeriod->dimona_period_employments()->create([
        'employment_id' => 'test-employment-id',
    ]);

    // Create a DimonaDeclaration with no anomalies
    $declaration = DimonaDeclaration::create([
        'dimona_period_id' => $dimonaPeriod->id,
        'type' => DimonaDeclarationType::In,
        'state' => DimonaDeclarationState::Pending,
        'payload' => [],
        'anomalies' => [],
    ]);

    // Act
    $service->handleExceptions($declaration);

    // Assert
    $exceptionCount = DimonaWorkerTypeException::where('social_security_number', '12345678907')->count();
    expect($exceptionCount)->toBe(0);
});

it('can be instantiated using the static new method', function () {
    // Just verify that the static new method returns an instance of WorkerTypeExceptionService
    $service = WorkerTypeExceptionService::new();

    expect($service)->toBeInstanceOf(WorkerTypeExceptionService::class);
});
