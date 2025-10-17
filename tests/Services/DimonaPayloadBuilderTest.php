<?php

use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Services\DimonaPayloadBuilder;

describe('build create payload', function () {

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

        $payload = DimonaPayloadBuilder::new()->buildCreatePayload($dimonaPeriod);

        expect($payload)->toBe([
            'employer' => [
                'enterpriseNumber' => '0123456789',
            ],
            'worker' => [
                'ssin' => '12345678910',
            ],
            'dimonaIn' => [
                'features' => [
                    'jointCommissionNumber' => 'XXX',
                    'workerType' => 'STU',
                ],
                'plannedHoursNumber' => 8.0,
                'studentPlaceOfWork' => [
                    'name' => 'Test Location',
                    'address' => [
                        'street' => 'Test Street',
                        'houseNumber' => '123',
                        'boxNumber' => null,
                        'postCode' => '1000',
                        'municipality' => [
                            'code' => 21004,
                            'name' => 'Brussels',
                        ],
                        'country' => 150,
                    ],
                ],
                'startDate' => '2023-01-01',
                'endDate' => '2023-01-01',
            ],
        ]);
    });

    it('builds a create payload for flexi worker type', function () {
        $dimonaPeriod = new DimonaPeriod([
            'employer_enterprise_number' => '0123456789',
            'worker_social_security_number' => '12.34.56-789.10',
            'joint_commission_number' => 302,
            'worker_type' => WorkerType::Flexi,
            'start_date' => '2023-01-01',
            'start_hour' => '09:00',
            'end_date' => '2023-01-01',
            'end_hour' => '17:00',
        ]);

        $payload = DimonaPayloadBuilder::new()->buildCreatePayload($dimonaPeriod);

        expect($payload)->toBe([
            'employer' => [
                'enterpriseNumber' => '0123456789',
            ],
            'worker' => [
                'ssin' => '12345678910',
            ],
            'dimonaIn' => [
                'features' => [
                    'jointCommissionNumber' => 'XXX',
                    'workerType' => 'FLX',
                ],
                'startDate' => '2023-01-01',
                'startHour' => '0900',
                'endDate' => '2023-01-01',
                'endHour' => '1700',
            ],
        ]);
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

        $payload = DimonaPayloadBuilder::new()->buildCreatePayload($dimonaPeriod);

        expect($payload)->toBe([
            'employer' => [
                'enterpriseNumber' => '0123456789',
            ],
            'worker' => [
                'ssin' => '12345678910',
            ],
            'dimonaIn' => [
                'features' => [
                    'jointCommissionNumber' => 200,
                    'workerType' => 'OTH',
                ],
                'startDate' => '2023-01-01',
                'endDate' => '2023-01-01',
            ],
        ]);
    });

});

describe('build update payload', function () {

    it('builds an update payload for student worker type', function () {
        $dimonaPeriod = new DimonaPeriod([
            'worker_type' => WorkerType::Student,
            'start_date' => '2023-01-01',
            'number_of_hours' => 8.0,
        ]);
        $dimonaPeriod->reference = '123456';

        $payload = DimonaPayloadBuilder::new()->buildUpdatePayload($dimonaPeriod);

        expect($payload)->toBe([
            'dimonaUpdate' => [
                'periodId' => 123456,
                'plannedHoursNumber' => 8.0,
                'startDate' => '2023-01-01',
                'endDate' => '2023-01-01',
            ],
        ]);
    });

    it('builds an update payload for flexi worker type', function () {
        $dimonaPeriod = new DimonaPeriod([
            'worker_type' => WorkerType::Flexi,
            'start_date' => '2023-01-01',
            'start_hour' => '09:00',
            'end_date' => '2023-01-01',
            'end_hour' => '17:00',
        ]);
        $dimonaPeriod->reference = '123456';

        $payload = DimonaPayloadBuilder::new()->buildUpdatePayload($dimonaPeriod);

        expect($payload)->toBe([
            'dimonaUpdate' => [
                'periodId' => 123456,
                'startDate' => '2023-01-01',
                'startHour' => '0900',
                'endDate' => '2023-01-01',
                'endHour' => '1700',
            ],
        ]);
    });

    it('builds an update payload for other worker type', function () {
        $dimonaPeriod = new DimonaPeriod([
            'worker_type' => WorkerType::Other,
            'start_date' => '2023-01-01',
        ]);
        $dimonaPeriod->reference = '123456';

        $payload = DimonaPayloadBuilder::new()->buildUpdatePayload($dimonaPeriod);

        expect($payload)->toBe([
            'dimonaUpdate' => [
                'periodId' => 123456,
                'startDate' => '2023-01-01',
                'endDate' => '2023-01-01',
            ],
        ]);
    });

});

describe('build cancel payload', function () {

    it('builds a cancel payload', function () {
        $dimonaPeriod = new DimonaPeriod;
        $dimonaPeriod->reference = '123456';

        $payload = DimonaPayloadBuilder::new()->buildCancelPayload(dimonaPeriod: $dimonaPeriod);

        expect($payload)->toBe([
            'dimonaCancel' => [
                'periodId' => 123456,
            ],
        ]);
    });

});
