<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\DimonaData;
use Hyperlab\Dimona\Data\DimonaLocationData;
use Hyperlab\Dimona\Enums\Country;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Services\DimonaPayloadBuilder;

beforeEach(function () {
    $this->builder = new DimonaPayloadBuilder;
});

it('builds a create payload for student worker type', function () {
    $dimonaData = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 302,
        workerType: WorkerType::Student,
        workerSocialSecurityNumber: '12.34.56-789.10',
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: Country::Belgium
        )
    );

    // Build the payload
    $payload = $this->builder->buildCreatePayload($dimonaData);

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
    $dimonaData = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 302,
        workerType: WorkerType::Flexi,
        workerSocialSecurityNumber: '12.34.56-789.10',
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: Country::Belgium
        )
    );

    // Build the payload
    $payload = $this->builder->buildCreatePayload($dimonaData);

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
    $dimonaData = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 200,
        workerType: WorkerType::Other,
        workerSocialSecurityNumber: '12.34.56-789.10',
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: Country::Belgium
        )
    );

    // Build the payload
    $payload = $this->builder->buildCreatePayload($dimonaData);

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
    $dimonaData = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 302,
        workerType: WorkerType::Student,
        workerSocialSecurityNumber: '12.34.56-789.10',
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: Country::Belgium
        )
    );

    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod;
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = $this->builder->buildUpdatePayload($dimonaPeriod, $dimonaData);

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaUpdate']['periodId'])->toBe(123456)
        ->and($payload['dimonaUpdate']['plannedHoursNumber'])->toBe(8.0)
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01');
});

it('builds an update payload for flexi worker type', function () {
    $dimonaData = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 302,
        workerType: WorkerType::Flexi,
        workerSocialSecurityNumber: '12.34.56-789.10',
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: Country::Belgium
        )
    );

    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod;
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = $this->builder->buildUpdatePayload($dimonaPeriod, $dimonaData);

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaUpdate']['periodId'])->toBe(123456)
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['startHour'])->toBe('1000')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endHour'])->toBe('1800');
});

it('builds an update payload for other worker type', function () {
    $dimonaData = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 200,
        workerType: WorkerType::Other,
        workerSocialSecurityNumber: '12.34.56-789.10',
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: Country::Belgium
        )
    );

    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod;
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = $this->builder->buildUpdatePayload($dimonaPeriod, $dimonaData);

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaUpdate']['periodId'])->toBe(123456)
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01');
});

it('builds a cancel payload', function () {
    $dimonaData = new DimonaData(
        employerEnterpriseNumber: '0123456789',
        jointCommissionNumber: 302,
        workerType: WorkerType::Student,
        workerSocialSecurityNumber: '12.34.56-789.10',
        startsAt: CarbonImmutable::parse('2023-01-01 09:00:00'),
        endsAt: CarbonImmutable::parse('2023-01-01 17:00:00'),
        location: new DimonaLocationData(
            name: 'Test Location',
            street: 'Test Street',
            houseNumber: '123',
            boxNumber: null,
            postalCode: '1000',
            place: 'Brussels',
            country: Country::Belgium
        )
    );

    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod;
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = $this->builder->buildCancelPayload($dimonaPeriod, $dimonaData);

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaCancel']['periodId'])->toBe(123456);
});
