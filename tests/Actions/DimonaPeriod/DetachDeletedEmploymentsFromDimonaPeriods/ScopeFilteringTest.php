<?php

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\DetachDeletedEmploymentsFromDimonaPeriods;
use Hyperlab\Dimona\Database\Factories\DimonaPeriodFactory;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    $this->period = CarbonPeriodImmutable::create('2025-10-01', '2025-10-31');
});

it('only affects periods within the specified date range', function () {
    $periodInRange = DimonaPeriodFactory::new()
        ->state([
            'start_date' => '2025-10-15',
            'end_date' => '2025-10-15',
            'state' => DimonaPeriodState::Accepted,
        ])
        ->withEmployments(['emp-deleted'])
        ->create();

    $periodOutOfRange = DimonaPeriodFactory::new()
        ->state([
            'start_date' => '2025-11-01',
            'end_date' => '2025-11-01',
            'state' => DimonaPeriodState::Accepted,
        ])
        ->withEmployments(['emp-deleted'])
        ->create();

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: new Collection([])
    );

    $inRangeCount = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $periodInRange->id)
        ->count();

    $outOfRangeCount = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $periodOutOfRange->id)
        ->count();

    expect($inRangeCount)->toBe(0)
        ->and($outOfRangeCount)->toBe(1);
});

it('only affects periods for the specified employer', function () {
    $matchingPeriod = DimonaPeriodFactory::new()
        ->state(['state' => DimonaPeriodState::Accepted])
        ->withEmployments(['emp-deleted'])
        ->create();

    $otherEmployerPeriod = DimonaPeriodFactory::new()
        ->state([
            'employer_enterprise_number' => '9999999999',
            'state' => DimonaPeriodState::Accepted,
        ])
        ->withEmployments(['emp-deleted'])
        ->create();

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: new Collection([])
    );

    $matchingCount = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $matchingPeriod->id)
        ->count();

    $otherCount = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $otherEmployerPeriod->id)
        ->count();

    expect($matchingCount)->toBe(0)
        ->and($otherCount)->toBe(1);
});

it('only affects periods for the specified worker', function () {
    $matchingPeriod = DimonaPeriodFactory::new()
        ->state(['state' => DimonaPeriodState::Accepted])
        ->withEmployments(['emp-deleted'])
        ->create();

    $otherWorkerPeriod = DimonaPeriodFactory::new()
        ->state([
            'worker_social_security_number' => '99999999999',
            'state' => DimonaPeriodState::Accepted,
        ])
        ->withEmployments(['emp-deleted'])
        ->create();

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: new Collection([])
    );

    $matchingCount = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $matchingPeriod->id)
        ->count();

    $otherCount = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $otherWorkerPeriod->id)
        ->count();

    expect($matchingCount)->toBe(0)
        ->and($otherCount)->toBe(1);
});
