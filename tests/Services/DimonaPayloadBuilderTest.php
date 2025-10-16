<?php

use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Services\DimonaPayloadBuilder;

it('builds a create payload for student worker type', function () {
    $dimonaPeriod = new DimonaPeriod([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12.34.56-789.10',
        'joint_commission_number' => 302,
        'worker_type' => WorkerType::Student,
        'start_date' => '2023-01-01',
        'start_hour' => null,
        'end_date' => '2023-01-01',
        'end_hour' => null,
        'number_of_hours' => 8.0,
        'location_name' => 'Test Location',
        'location_street' => 'Test Street',
        'location_house_number' => '123',
        'location_box_number' => null,
        'location_postal_code' => '1000',
        'location_place' => 'Brussels',
        'location_country' => EmploymentLocationCountry::Belgium,
    ]);

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildCreatePayload($dimonaPeriod);

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
    $dimonaPeriod = new DimonaPeriod([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12.34.56-789.10',
        'joint_commission_number' => 302,
        'worker_type' => WorkerType::Flexi,
        'start_date' => '2023-01-01',
        'start_hour' => '0900',
        'end_date' => '2023-01-01',
        'end_hour' => '1700',
    ]);

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildCreatePayload($dimonaPeriod);

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['employer']['enterpriseNumber'])->toBe('0123456789')
        ->and($payload['worker']['ssin'])->toBe('12345678910')
        ->and($payload['dimonaIn']['features']['jointCommissionNumber'])->toBe('XXX')
        ->and($payload['dimonaIn']['features']['workerType'])->toBe('FLX')
        ->and($payload['dimonaIn']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['startHour'])->toBe('0900')
        ->and($payload['dimonaIn']['endDate'])->toBe('2023-01-01')
        ->and($payload['dimonaIn']['endHour'])->toBe('1700');
});

it('builds a create payload for other worker type', function () {
    $dimonaPeriod = new DimonaPeriod([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12.34.56-789.10',
        'joint_commission_number' => 200,
        'worker_type' => WorkerType::Other,
        'start_date' => '2023-01-01',
        'start_hour' => null,
        'end_date' => '2023-01-01',
        'end_hour' => null,
    ]);

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildCreatePayload($dimonaPeriod);

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
    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod([
        'worker_type' => WorkerType::Student,
        'start_date' => '2023-01-01',
        'number_of_hours' => 8.0,
    ]);
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildUpdatePayload($dimonaPeriod);

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaUpdate']['periodId'])->toBe(123456)
        ->and($payload['dimonaUpdate']['plannedHoursNumber'])->toBe(8.0)
        ->and($payload['dimonaUpdate']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaUpdate']['endDate'])->toBe('2023-01-01');
});

it('builds an update payload for flexi worker type', function () {
    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod([
        'worker_type' => WorkerType::Flexi,
        'start_date' => '2023-01-01',
        'start_hour' => '0900',
        'end_date' => '2023-01-01',
        'end_hour' => '1700',
    ]);
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildUpdatePayload($dimonaPeriod);

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaUpdate']['periodId'])->toBe(123456)
        ->and($payload['dimonaUpdate']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaUpdate']['startHour'])->toBe('0900')
        ->and($payload['dimonaUpdate']['endDate'])->toBe('2023-01-01')
        ->and($payload['dimonaUpdate']['endHour'])->toBe('1700');
});

it('builds an update payload for other worker type', function () {
    // Create a DimonaPeriod instance
    $dimonaPeriod = new DimonaPeriod([
        'worker_type' => WorkerType::Other,
        'start_date' => '2023-01-01',
    ]);
    $dimonaPeriod->reference = '123456';

    // Build the payload
    $payload = DimonaPayloadBuilder::new()->buildUpdatePayload($dimonaPeriod);

    // Assert the payload structure
    expect($payload)->toBeArray()
        ->and($payload['dimonaUpdate']['periodId'])->toBe(123456)
        ->and($payload['dimonaUpdate']['startDate'])->toBe('2023-01-01')
        ->and($payload['dimonaUpdate']['endDate'])->toBe('2023-01-01');
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
