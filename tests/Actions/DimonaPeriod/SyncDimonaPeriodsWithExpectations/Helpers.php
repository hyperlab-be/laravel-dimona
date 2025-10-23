<?php

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\SyncDimonaPeriodsWithExpectations;
use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

function setupTestContext(): void
{
    test()->employerNumber = '0123456789';
    test()->workerSsn = '12345678901';

    test()->defaultLocation = new EmploymentLocationData(
        name: 'Test Office',
        street: 'Test Street',
        houseNumber: '123',
        boxNumber: null,
        postalCode: '1000',
        place: 'Brussels',
        country: EmploymentLocationCountry::Belgium
    );
}

function makeExpectedPeriod(array $overrides = []): DimonaPeriodData
{
    $defaults = [
        'employmentIds' => ['emp-1'],
        'employerEnterpriseNumber' => test()->employerNumber,
        'workerSocialSecurityNumber' => test()->workerSsn,
        'jointCommissionNumber' => 304,
        'workerType' => WorkerType::Flexi,
        'startDate' => '2025-10-01',
        'startHour' => '08:00',
        'endDate' => '2025-10-01',
        'endHour' => '12:00',
        'numberOfHours' => null,
        'location' => test()->defaultLocation,
    ];

    $data = array_merge($defaults, $overrides);

    return new DimonaPeriodData(
        employmentIds: $data['employmentIds'],
        employerEnterpriseNumber: $data['employerEnterpriseNumber'],
        workerSocialSecurityNumber: $data['workerSocialSecurityNumber'],
        jointCommissionNumber: $data['jointCommissionNumber'],
        workerType: $data['workerType'],
        startDate: $data['startDate'],
        startHour: $data['startHour'],
        endDate: $data['endDate'],
        endHour: $data['endHour'],
        numberOfHours: $data['numberOfHours'],
        location: $data['location']
    );
}

function syncPeriods(Collection $expectedPeriods): void
{
    SyncDimonaPeriodsWithExpectations::new()->execute(
        employerEnterpriseNumber: test()->employerNumber,
        workerSocialSecurityNumber: test()->workerSsn,
        period: CarbonPeriodImmutable::create('2025-09-01', '2025-11-01'),
        expectedDimonaPeriods: $expectedPeriods
    );
}

function getEmploymentIds(DimonaPeriod $period): array
{
    return DB::table('dimona_period_employment')
        ->where('dimona_period_id', $period->id)
        ->pluck('employment_id')
        ->sort()
        ->values()
        ->toArray();
}

function makePeriod(array $overrides = [], array $employmentIds = []): DimonaPeriod
{
    $defaults = [
        'employer_enterprise_number' => test()->employerNumber,
        'worker_social_security_number' => test()->workerSsn,
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '12:00',
        'state' => DimonaPeriodState::Accepted,
    ];

    $factory = DimonaPeriod::factory()->state(array_merge($defaults, $overrides));

    if ($employmentIds) {
        $factory = $factory->withEmployments($employmentIds);
    }

    return $factory->create();
}
