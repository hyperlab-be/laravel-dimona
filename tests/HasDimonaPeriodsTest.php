<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Models\Employment;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

it('has a relationship to dimona periods', function () {
    $employment = Employment::query()->create();
    $relationship = $employment->dimona_periods();

    expect($relationship)->toBeInstanceOf(BelongsToMany::class)
        ->and($relationship->getRelated())->toBeInstanceOf(DimonaPeriod::class);
});

it('only returns dimona periods that are attached via pivot table', function () {
    $employment = Employment::query()->create();
    $otherEmployment = Employment::query()->create();

    // Create a DimonaPeriod that includes this employment
    $period = DimonaPeriod::query()->create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 200,
        'worker_type' => WorkerType::Student,
        'starts_at' => CarbonImmutable::parse('2025-10-01 08:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 17:00'),
        'employment_ids' => [$employment->id],
        'state' => DimonaPeriodState::Pending,
    ]);
    $employment->dimona_periods()->attach($period);

    // Create a DimonaPeriod that includes both employments
    $sharedPeriod = DimonaPeriod::query()->create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 200,
        'worker_type' => WorkerType::Student,
        'starts_at' => CarbonImmutable::parse('2025-10-02 08:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-02 17:00'),
        'employment_ids' => [$employment->id, $otherEmployment->id],
        'state' => DimonaPeriodState::Pending,
    ]);
    $employment->dimona_periods()->attach($sharedPeriod);
    $otherEmployment->dimona_periods()->attach($sharedPeriod);

    // Create a DimonaPeriod for a different employment
    $otherPeriod = DimonaPeriod::query()->create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 200,
        'worker_type' => WorkerType::Student,
        'starts_at' => CarbonImmutable::parse('2025-10-03 08:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-03 17:00'),
        'employment_ids' => [$otherEmployment->id],
        'state' => DimonaPeriodState::Pending,
    ]);
    $otherEmployment->dimona_periods()->attach($otherPeriod);

    $periods = $employment->dimona_periods;

    expect($periods)->toHaveCount(2)
        ->and($periods->pluck('id')->toArray())->toContain($period->id, $sharedPeriod->id)
        ->and($periods->pluck('id')->toArray())->not->toContain($otherPeriod->id);
});
