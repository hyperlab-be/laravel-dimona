<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Services\DimonaPayloadBuilder;

it('builds a create payload for student worker type', function () {
    $dimonaPeriodData = new DimonaPeriodData(
        employmentIds: ['emp-1', 'emp-2'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12.34.56-789.10',
        jointCommissionNumber: 302,
        workerType: WorkerType::Student,
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new EmploymentLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: EmploymentLocationCountry::Belgium
        )
    );

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildCreatePayload(
        dimonaPeriodData: $dimonaPeriodData
    );

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['employer']['enterpriseNumber'])->toBe('0123456789')
        ->and($payload['worker']['ssin'])->toBe('12345678910')
        ->and($payload['dimonaIn']['features']['jointCommissionNumber'])->toBe('XXX')
        ->and($payload['dimonaIn']['features']['workerType'])->toBe('STU')
        ->and($payload['dimonaIn']['plannedHoursNumber'])->toBe(8.0)
        ->and($payload['dimonaIn']['studentPlaceOfWork']['name'])->toBe('Test Location')
        ->and($payload['dimonaIn']['studentPlaceOfWork']['address']['street'])->toBe('Test Street')
        ->and($payload['dimonaIn']['studentPlaceOfWork']['address']['houseNumber'])->toBe('123')
        ->and($payload['dimonaIn']['studentPlaceOfWork']['address']['postCode'])->toBe('1000')
        ->and($payload['dimonaIn']['studentPlaceOfWork']['address']['municipality']['code'])->toBe(21004)
        ->and($payload['dimonaIn']['studentPlaceOfWork']['address']['municipality']['name'])->toBe('Brussels')
        ->and($payload['dimonaIn']['studentPlaceOfWork']['address']['country'])->toBe(150)
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01');
});

it('builds a create payload for flexi worker type', function () {
    $dimonaPeriodData = new DimonaPeriodData(
        employmentIds: ['emp-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12.34.56-789.10',
        jointCommissionNumber: 302,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new EmploymentLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: EmploymentLocationCountry::Belgium
        )
    );

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildCreatePayload(
        dimonaPeriodData: $dimonaPeriodData
    );

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['employer']['enterpriseNumber'])->toBe('0123456789')
        ->and($payload['worker']['ssin'])->toBe('12345678910')
        ->and($payload['dimonaIn']['features']['jointCommissionNumber'])->toBe('XXX')
        ->and($payload['dimonaIn']['features']['workerType'])->toBe('FLX')
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['startHour'])->toBe('1000')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endHour'])->toBe('1800');
});

it('builds a create payload for other worker type', function () {
    $dimonaPeriodData = new DimonaPeriodData(
        employmentIds: ['emp-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12.34.56-789.10',
        jointCommissionNumber: 200,
        workerType: WorkerType::Other,
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new EmploymentLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: EmploymentLocationCountry::Belgium
        )
    );

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildCreatePayload(
        dimonaPeriodData: $dimonaPeriodData
    );

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['employer']['enterpriseNumber'])->toBe('0123456789')
        ->and($payload['worker']['ssin'])->toBe('12345678910')
        ->and($payload['dimonaIn']['features']['jointCommissionNumber'])->toBe(200)
        ->and($payload['dimonaIn']['features']['workerType'])->toBe('OTH')
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01');
});

it('builds an update payload for student worker type', function () {
    $dimonaPeriodData = new DimonaPeriodData(
        employmentIds: ['emp-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12.34.56-789.10',
        jointCommissionNumber: 302,
        workerType: WorkerType::Student,
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new EmploymentLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: EmploymentLocationCountry::Belgium
        )
    );

    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod;
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildUpdatePayload(
        dimonaPeriod: $dimonaPeriod,
        dimonaPeriodData: $dimonaPeriodData
    );

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaUpdate']['periodId'])->toBe(123456)
        ->and($payload['dimonaUpdate']['plannedHoursNumber'])->toBe(8.0)
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01');
});

it('builds an update payload for flexi worker type', function () {
    $dimonaPeriodData = new DimonaPeriodData(
        employmentIds: ['emp-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12.34.56-789.10',
        jointCommissionNumber: 302,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new EmploymentLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: EmploymentLocationCountry::Belgium
        )
    );

    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod;
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildUpdatePayload(
        dimonaPeriod: $dimonaPeriod,
        dimonaPeriodData: $dimonaPeriodData
    );

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaUpdate']['periodId'])->toBe(123456)
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['startHour'])->toBe('1000')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endHour'])->toBe('1800');
});

it('builds an update payload for other worker type', function () {
    $dimonaPeriodData = new DimonaPeriodData(
        employmentIds: ['emp-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12.34.56-789.10',
        jointCommissionNumber: 200,
        workerType: WorkerType::Other,
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new EmploymentLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: EmploymentLocationCountry::Belgium
        )
    );

    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod;
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildUpdatePayload(
        dimonaPeriod: $dimonaPeriod,
        dimonaPeriodData: $dimonaPeriodData
    );

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaUpdate']['periodId'])->toBe(123456)
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01');
});

it('builds a cancel payload', function () {
    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod;
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildCancelPayload(dimonaPeriod: $dimonaPeriod);

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaCancel']['periodId'])->toBe(123456);
});
