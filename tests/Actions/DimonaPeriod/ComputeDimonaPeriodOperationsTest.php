<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeDimonaPeriodOperations;
use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Enums\DimonaPeriodOperation;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;

it('returns empty diffs when expected and actual are both empty', function () {
    $expected = collect();
    $actual = collect();

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toBeEmpty();
});

it('generates create diffs for new expected periods with no actual', function () {
    $expectedPeriod = new DimonaPeriodData(
        employmentIds: ['employment-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $expected = collect([collect([$expectedPeriod])]);
    $actual = collect();

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(1)
        ->and($result[0]->type)->toBe(DimonaPeriodOperation::Create)
        ->and($result[0]->expected)->toBe($expectedPeriod)
        ->and($result[0]->actual)->toBeNull();
});

it('generates cancel diffs for actual periods with no expected', function () {
    $actualPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::Accepted,
        'employment_ids' => ['employment-1'],
    ]);

    $expected = collect();
    $actual = collect([$actualPeriod]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(1)
        ->and($result[0]->type)->toBe(DimonaPeriodOperation::Cancel)
        ->and($result[0]->expected)->toBeNull()
        ->and($result[0]->actual->id)->toBe($actualPeriod->id);
});

it('generates update diff when dates differ', function () {
    $expectedPeriod = new DimonaPeriodData(
        employmentIds: ['employment-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 13:00'), // Different end time
        location: EmploymentDataFactory::new()->create()->location,
    );

    $actualPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::Accepted,
        'employment_ids' => ['employment-1'],
    ]);

    $expected = collect([collect([$expectedPeriod])]);
    $actual = collect([$actualPeriod]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(1)
        ->and($result[0]->type)->toBe(DimonaPeriodOperation::Update)
        ->and($result[0]->expected)->toBe($expectedPeriod)
        ->and($result[0]->actual->id)->toBe($actualPeriod->id);
});

it('generates update diff when employment IDs differ', function () {
    $expectedPeriod = new DimonaPeriodData(
        employmentIds: ['employment-1', 'employment-2'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $actualPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::Accepted,
        'employment_ids' => ['employment-1'],
    ]);

    $expected = collect([collect([$expectedPeriod])]);
    $actual = collect([$actualPeriod]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(1)
        ->and($result[0]->type)->toBe(DimonaPeriodOperation::Update)
        ->and($result[0]->expected)->toBe($expectedPeriod)
        ->and($result[0]->actual->id)->toBe($actualPeriod->id);
});

it('generates no diff when expected and actual match perfectly', function () {
    $expectedPeriod = new DimonaPeriodData(
        employmentIds: ['employment-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $actualPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::Accepted,
        'employment_ids' => ['employment-1'],
    ]);

    $expected = collect([collect([$expectedPeriod])]);
    $actual = collect([$actualPeriod]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toBeEmpty();
});

it('skips pending periods from operations', function () {
    $expectedPeriod = new DimonaPeriodData(
        employmentIds: ['employment-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 13:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $actualPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::Pending,
        'employment_ids' => ['employment-1'],
    ]);

    $expected = collect([collect([$expectedPeriod])]);
    $actual = collect([$actualPeriod]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toBeEmpty();
});

it('generates cancel operation for periods with AcceptedWithWarning state', function () {
    $expectedPeriod = new DimonaPeriodData(
        employmentIds: ['employment-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $actualPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::AcceptedWithWarning,
        'employment_ids' => ['employment-1'],
    ]);

    $expected = collect([collect([$expectedPeriod])]);
    $actual = collect([$actualPeriod]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(1)
        ->and($result[0]->type)->toBe(DimonaPeriodOperation::Cancel)
        ->and($result[0]->expected)->toBeNull()
        ->and($result[0]->actual->id)->toBe($actualPeriod->id);
});

it('matches periods by overlapping employment IDs', function () {
    $expectedPeriod = new DimonaPeriodData(
        employmentIds: ['employment-1', 'employment-2'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 15:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $actualPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::Accepted,
        'employment_ids' => ['employment-1'], // Overlapping ID
    ]);

    $expected = collect([collect([$expectedPeriod])]);
    $actual = collect([$actualPeriod]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(1)
        ->and($result[0]->type)->toBe(DimonaPeriodOperation::Update)
        ->and($result[0]->actual->id)->toBe($actualPeriod->id);
});

it('handles multiple expected periods in the same group', function () {
    $expected1 = new DimonaPeriodData(
        employmentIds: ['employment-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $expected2 = new DimonaPeriodData(
        employmentIds: ['employment-2'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 13:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 17:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $expected = collect([collect([$expected1, $expected2])]);
    $actual = collect();

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(2)
        ->and($result[0]->type)->toBe(DimonaPeriodOperation::Create)
        ->and($result[1]->type)->toBe(DimonaPeriodOperation::Create);
});

it('handles different worker types separately', function () {
    $expectedFlexi = new DimonaPeriodData(
        employmentIds: ['employment-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $expectedStudent = new DimonaPeriodData(
        employmentIds: ['employment-2'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Student,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $actualFlexi = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::Accepted,
        'employment_ids' => ['employment-1'],
    ]);

    $expected = collect([
        collect([$expectedFlexi]),
        collect([$expectedStudent]),
    ]);
    $actual = collect([$actualFlexi]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(1)
        ->and($result[0]->type)->toBe(DimonaPeriodOperation::Create)
        ->and($result[0]->expected->workerType)->toBe(WorkerType::Student);
});

it('handles complex scenario with multiple operations', function () {
    // Expected periods
    $expected1 = new DimonaPeriodData(
        employmentIds: ['employment-1', 'employment-2'], // Updated
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $expected2 = new DimonaPeriodData(
        employmentIds: ['employment-3'], // New
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 13:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 17:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    // Actual periods
    $actual1 = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::Accepted,
        'employment_ids' => ['employment-1'], // Will be updated
    ]);

    $actual2 = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 18:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 22:00'),
        'state' => DimonaPeriodState::Accepted,
        'employment_ids' => ['employment-old'], // Will be cancelled
    ]);

    $expected = collect([collect([$expected1, $expected2])]);
    $actual = collect([$actual1, $actual2]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(3);

    $updateDiff = $result->firstWhere('type', DimonaPeriodOperation::Update);
    expect($updateDiff)->not->toBeNull()
        ->and($updateDiff->actual->id)->toBe($actual1->id);

    $createDiff = $result->firstWhere('type', DimonaPeriodOperation::Create);
    expect($createDiff)->not->toBeNull()
        ->and($createDiff->expected->employmentIds)->toBe(['employment-3']);

    $cancelDiff = $result->firstWhere('type', DimonaPeriodOperation::Cancel);
    expect($cancelDiff)->not->toBeNull()
        ->and($cancelDiff->actual->id)->toBe($actual2->id);
});

it('handles periods from different days separately', function () {
    $expectedDay1 = new DimonaPeriodData(
        employmentIds: ['employment-1'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $expectedDay2 = new DimonaPeriodData(
        employmentIds: ['employment-2'],
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-02 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-02 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $expected = collect([
        collect([$expectedDay1]),
        collect([$expectedDay2]),
    ]);
    $actual = collect();

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toHaveCount(2)
        ->and($result[0]->type)->toBe(DimonaPeriodOperation::Create)
        ->and($result[1]->type)->toBe(DimonaPeriodOperation::Create);
});

it('ignores employment ID order when comparing', function () {
    $expectedPeriod = new DimonaPeriodData(
        employmentIds: ['employment-2', 'employment-1'], // Different order
        employerEnterpriseNumber: '0123456789',
        workerSocialSecurityNumber: '12345678901',
        jointCommissionNumber: 304,
        workerType: WorkerType::Flexi,
        startsAt: CarbonImmutable::parse('2025-10-01 07:00'),
        endsAt: CarbonImmutable::parse('2025-10-01 12:00'),
        location: EmploymentDataFactory::new()->create()->location,
    );

    $actualPeriod = DimonaPeriod::create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 304,
        'worker_type' => WorkerType::Flexi,
        'starts_at' => CarbonImmutable::parse('2025-10-01 07:00'),
        'ends_at' => CarbonImmutable::parse('2025-10-01 12:00'),
        'state' => DimonaPeriodState::Accepted,
        'employment_ids' => ['employment-1', 'employment-2'], // Different order
    ]);

    $expected = collect([collect([$expectedPeriod])]);
    $actual = collect([$actualPeriod]);

    $result = ComputeDimonaPeriodOperations::new()->execute($expected, $actual);

    expect($result)->toBeEmpty();
});
