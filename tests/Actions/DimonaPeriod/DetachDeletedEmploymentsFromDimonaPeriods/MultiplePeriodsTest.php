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

it('handles multiple periods with mixed employment links', function () {
    $period1 = DimonaPeriodFactory::new()
        ->state([
            'start_date' => '2025-10-01',
            'end_date' => '2025-10-01',
            'state' => DimonaPeriodState::Accepted,
        ])
        ->withEmployments(['emp-1', 'emp-deleted'])
        ->create();

    $period2 = DimonaPeriodFactory::new()
        ->state([
            'start_date' => '2025-10-02',
            'end_date' => '2025-10-02',
            'state' => DimonaPeriodState::Accepted,
        ])
        ->withEmployments(['emp-2', 'emp-deleted'])
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

    $period1Links = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $period1->id)
        ->pluck('employment_id')
        ->toArray();

    $period2Links = DB::table('dimona_period_employment')
        ->where('dimona_period_id', $period2->id)
        ->pluck('employment_id')
        ->toArray();

    expect($period1Links)->toBe(['emp-1'])
        ->and($period2Links)->toBe(['emp-2']);
});
