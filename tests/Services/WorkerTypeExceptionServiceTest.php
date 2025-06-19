<?php

use Hyperlab\Dimona\Data\DimonaData;
use Hyperlab\Dimona\Data\DimonaLocationData;
use Hyperlab\Dimona\Enums\Country;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaWorkerTypeException;
use Hyperlab\Dimona\Services\WorkerTypeExceptionService;
use Illuminate\Support\Carbon;

it('resolves worker type to Other when exception exists for Flexi worker', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = Carbon::parse('2023-01-15 10:00:00');
    $endsAt = Carbon::parse('2023-01-15 14:00:00');

    // Create an exception in the database
    DimonaWorkerTypeException::create([
        'social_security_number' => '12345678901',
        'worker_type' => WorkerType::Flexi,
        'starts_at' => $startsAt->copy()->startOfDay(),
        'ends_at' => $startsAt->copy()->endOfDay(),
    ]);

    $data = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 123,
        workerType: WorkerType::Flexi,
        workerSocialSecurityNumber: '12345678901',
        startsAt: $startsAt,
        endsAt: $endsAt,
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '1',
            boxNumber: null,
            postalCode: '1000',
            place: 'Test City',
            country: Country::Belgium
        )
    );

    // Act
    $resolvedType = $service->resolveWorkerType($data);

    // Assert
    expect($resolvedType)->toBe(WorkerType::Other);
});

it('resolves worker type to Other when exception exists for Student worker', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = Carbon::parse('2023-01-15 10:00:00');
    $endsAt = Carbon::parse('2023-01-15 14:00:00');

    // Create an exception in the database
    DimonaWorkerTypeException::create([
        'social_security_number' => '12345678901',
        'worker_type' => WorkerType::Student,
        'starts_at' => $startsAt->copy()->startOfDay(),
        'ends_at' => $startsAt->copy()->endOfDay(),
    ]);

    $data = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 123,
        workerType: WorkerType::Student,
        workerSocialSecurityNumber: '12345678901',
        startsAt: $startsAt,
        endsAt: $endsAt,
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '1',
            boxNumber: null,
            postalCode: '1000',
            place: 'Test City',
            country: Country::Belgium
        )
    );

    // Act
    $resolvedType = $service->resolveWorkerType($data);

    // Assert
    expect($resolvedType)->toBe(WorkerType::Other);
});

it('keeps original worker type when no exception exists for Flexi worker', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = Carbon::parse('2023-01-15 10:00:00');
    $endsAt = Carbon::parse('2023-01-15 14:00:00');

    // Make sure no exception exists for this worker
    DimonaWorkerTypeException::where('social_security_number', '12345678902')->delete();

    $data = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 123,
        workerType: WorkerType::Flexi,
        workerSocialSecurityNumber: '12345678902',
        startsAt: $startsAt,
        endsAt: $endsAt,
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '1',
            boxNumber: null,
            postalCode: '1000',
            place: 'Test City',
            country: Country::Belgium
        )
    );

    // Act
    $resolvedType = $service->resolveWorkerType($data);

    // Assert
    expect($resolvedType)->toBe(WorkerType::Flexi);
});

it('keeps original worker type when no exception exists for Student worker', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = Carbon::parse('2023-01-15 10:00:00');
    $endsAt = Carbon::parse('2023-01-15 14:00:00');

    // Make sure no exception exists for this worker
    DimonaWorkerTypeException::where('social_security_number', '12345678903')->delete();

    $data = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 123,
        workerType: WorkerType::Student,
        workerSocialSecurityNumber: '12345678903',
        startsAt: $startsAt,
        endsAt: $endsAt,
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '1',
            boxNumber: null,
            postalCode: '1000',
            place: 'Test City',
            country: Country::Belgium
        )
    );

    // Act
    $resolvedType = $service->resolveWorkerType($data);

    // Assert
    expect($resolvedType)->toBe(WorkerType::Student);
});

it('keeps original worker type when worker type is not Flexi or Student', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = Carbon::parse('2023-01-15 10:00:00');
    $endsAt = Carbon::parse('2023-01-15 14:00:00');

    $data = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 123,
        workerType: WorkerType::Other,
        workerSocialSecurityNumber: '12345678904',
        startsAt: $startsAt,
        endsAt: $endsAt,
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '1',
            boxNumber: null,
            postalCode: '1000',
            place: 'Test City',
            country: Country::Belgium
        )
    );

    // Act
    $resolvedType = $service->resolveWorkerType($data);

    // Assert
    expect($resolvedType)->toBe(WorkerType::Other);
});

it('creates a flexi exception when flexi requirements are not met', function () {
    // Arrange
    $service = new WorkerTypeExceptionService;

    $startsAt = Carbon::parse('2023-01-15 10:00:00');
    $endsAt = Carbon::parse('2023-01-15 14:00:00');

    $data = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 123,
        workerType: WorkerType::Flexi,
        workerSocialSecurityNumber: '12345678905',
        startsAt: $startsAt,
        endsAt: $endsAt,
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '1',
            boxNumber: null,
            postalCode: '1000',
            place: 'Test City',
            country: Country::Belgium
        )
    );

    $declaration = new DimonaDeclaration;
    $declaration->anomalies = ['90017-510'];

    // Act
    $service->handleExceptions($declaration, $data);

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

    $startsAt = Carbon::parse('2023-01-15 10:00:00');
    $endsAt = Carbon::parse('2023-01-15 14:00:00');

    $data = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 123,
        workerType: WorkerType::Student,
        workerSocialSecurityNumber: '12345678906',
        startsAt: $startsAt,
        endsAt: $endsAt,
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '1',
            boxNumber: null,
            postalCode: '1000',
            place: 'Test City',
            country: Country::Belgium
        )
    );

    // Mock the DimonaDeclaration anomalies
    $declaration = new DimonaDeclaration;
    $declaration->anomalies = ['90017-369'];

    // Act
    $service->handleExceptions($declaration, $data);

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

    $startsAt = Carbon::parse('2023-01-15 10:00:00');
    $endsAt = Carbon::parse('2023-01-15 14:00:00');

    $data = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 123,
        workerType: WorkerType::Flexi,
        workerSocialSecurityNumber: '12345678907',
        startsAt: $startsAt,
        endsAt: $endsAt,
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '1',
            boxNumber: null,
            postalCode: '1000',
            place: 'Test City',
            country: Country::Belgium
        )
    );

    // Make sure no exception exists for this worker
    DimonaWorkerTypeException::where('social_security_number', '12345678907')->delete();

    // Mock the DimonaDeclaration anomalies
    $declaration = new DimonaDeclaration;
    $declaration->anomalies = [];

    // Act
    $service->handleExceptions($declaration, $data);

    // Assert
    $exceptionCount = DimonaWorkerTypeException::where('social_security_number', '12345678907')->count();
    expect($exceptionCount)->toBe(0);
});

it('can be instantiated using the static new method', function () {
    // Just verify that the static new method returns an instance of WorkerTypeExceptionService
    $service = WorkerTypeExceptionService::new();

    expect($service)->toBeInstanceOf(WorkerTypeExceptionService::class);
});
