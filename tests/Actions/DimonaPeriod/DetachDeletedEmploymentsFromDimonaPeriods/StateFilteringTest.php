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

it('does not affect cancelled periods', function () {
    $cancelledPeriod = DimonaPeriodFactory::new()
        ->state(['state' => DimonaPeriodState::Cancelled])
        ->withEmployments(['emp-1'])
        ->create();

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: new Collection([])
    );

    $count = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $cancelledPeriod->id)
        ->count();

    expect($count)->toBe(1);
});

it('does not affect failed periods', function () {
    $failedPeriod = DimonaPeriodFactory::new()
        ->state(['state' => DimonaPeriodState::Failed])
        ->withEmployments(['emp-1'])
        ->create();

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: new Collection([])
    );

    $count = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $failedPeriod->id)
        ->count();

    expect($count)->toBe(1);
});

it('affects periods with other states', function () {
    $states = [
        DimonaPeriodState::New,
        DimonaPeriodState::Pending,
        DimonaPeriodState::Waiting,
        DimonaPeriodState::Accepted,
        DimonaPeriodState::AcceptedWithWarning,
        DimonaPeriodState::Refused,
    ];

    $periods = [];
    foreach ($states as $state) {
        $periods[] = DimonaPeriodFactory::new()
            ->state(['state' => $state])
            ->withEmployments(['emp-deleted'])
            ->create();
    }

    DetachDeletedEmploymentsFromDimonaPeriods::new()->execute(
        employerEnterpriseNumber: EMPLOYER_ENTERPRISE_NUMBER,
        workerSocialSecurityNumber: WORKER_SSN,
        period: $this->period,
        employments: new Collection([])
    );

    foreach ($periods as $period) {
        $count = DB::table('dimona_period_employment')
            ->where('dimona_period_id', $period->id)
            ->count();

        expect($count)->toBe(0);
    }
});
