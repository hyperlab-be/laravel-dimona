<?php

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\DetachDeletedEmploymentsFromDimonaPeriods;
use Hyperlab\Dimona\Database\Factories\DimonaPeriodFactory;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    $this->period = CarbonPeriodImmutable::create('2025-10-01', '2025-10-31');
});

it('removes employment link that is not in the current employments list', function () {
    $dimonaPeriod = DimonaPeriodFactory::new()
        ->state(['state' => DimonaPeriodState::Accepted])
        ->withEmployments(['emp-1', 'emp-2', 'emp-3'])
        ->create();

    $employments = new Collection([
        EmploymentDataFactory::new()->id('emp-1')->create(),
        EmploymentDataFactory::new()->id('emp-2')->create(),
    ]);

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: $employments
    );

    $remainingEmploymentIds = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $dimonaPeriod->id)
        ->pluck('employment_id')
        ->sort()
        ->values()
        ->toArray();

    expect($remainingEmploymentIds)->toBe(['emp-1', 'emp-2']);
});

it('removes all employment links when employments list is empty', function () {
    $dimonaPeriod = DimonaPeriodFactory::new()
        ->state(['state' => DimonaPeriodState::Accepted])
        ->withEmployments(['emp-1', 'emp-2'])
        ->create();

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: new Collection([])
    );

    $count = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $dimonaPeriod->id)
        ->count();

    expect($count)->toBe(0);
});

it('keeps all employment links when all are in the current list', function () {
    $dimonaPeriod = DimonaPeriodFactory::new()
        ->state(['state' => DimonaPeriodState::Accepted])
        ->withEmployments(['emp-1', 'emp-2'])
        ->create();

    $employments = new Collection([
        EmploymentDataFactory::new()->id('emp-1')->create(),
        EmploymentDataFactory::new()->id('emp-2')->create(),
    ]);

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: $employments
    );

    $count = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $dimonaPeriod->id)
        ->count();

    expect($count)->toBe(2);
});

it('does nothing when there are no employment links to remove', function () {
    $dimonaPeriod = DimonaPeriodFactory::new()
        ->state(['state' => DimonaPeriodState::Accepted])
        ->create();

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: new Collection([])
    );

    $count = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $dimonaPeriod->id)
        ->count();

    expect($count)->toBe(0);
});
