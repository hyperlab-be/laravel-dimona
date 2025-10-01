<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeDimonaPeriods;
use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

it('groups employments by employer attributes', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->employerEnterpriseNumber('0778603756')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi)
        ->workerSocialSecurityNumber('95011426556');

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
        $baseFactory
            ->id('employment-3')
            ->employerEnterpriseNumber('0544881959')
            ->jointCommissionNumber(302)
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(DimonaPeriodData::class)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->employerEnterpriseNumber)->toBe('0778603756')
        ->and($result[1]->employmentIds)->toBe(['employment-3'])
        ->and($result[1]->employerEnterpriseNumber)->toBe('0544881959');
});

it('merges consecutive employments into single dimona period', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->employerEnterpriseNumber('0778603756')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi)
        ->workerSocialSecurityNumber('95011426556');

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 17:00'));
});

it('creates separate periods for non-consecutive employments', function () {
    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        EmploymentDataFactory::new()
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 13:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 12:00'))
        ->and($result[1]->employmentIds)->toBe(['employment-2'])
        ->and($result[1]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 13:00'))
        ->and($result[1]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 17:00'));
});

it('merges multiple consecutive employments', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->employerEnterpriseNumber('0778603756')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi)
        ->workerSocialSecurityNumber('95011426556');

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 15:00'))
            ->create(),
        $baseFactory
            ->id('employment-3')
            ->startsAt(CarbonImmutable::parse('2025-10-01 15:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 18:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2', 'employment-3'])
        ->and($result[0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 18:00'));
});

it('separates periods by different worker types', function () {
    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->workerType(WorkerType::Flexi)
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        EmploymentDataFactory::new()
            ->id('employment-2')
            ->workerType(WorkerType::Student)
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->workerType)->toBe(WorkerType::Flexi)
        ->and($result[1]->workerType)->toBe(WorkerType::Student);
});

it('separates periods by different dates', function () {
    $employments = new Collection([
        EmploymentDataFactory::new()
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        EmploymentDataFactory::new()
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-02 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-02 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(2);
});

it('handles unordered employments', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->employerEnterpriseNumber('0778603756')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi)
        ->workerSocialSecurityNumber('95011426556');

    $employments = new Collection([
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 17:00'));
});

it('handles empty collection', function () {
    $employments = new Collection([]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toBeEmpty();
});

it('handles single employment', function () {
    $employment = EmploymentDataFactory::new()
        ->id('employment-1')
        ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
        ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
        ->create();

    $result = ComputeDimonaPeriods::new()->execute(new Collection([$employment]));

    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 07:00'))
        ->and($result[0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-01 12:00'));
});

it('separates periods by different joint commission numbers', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->employerEnterpriseNumber('0778603756')
        ->workerType(WorkerType::Flexi)
        ->workerSocialSecurityNumber('95011426556');

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->jointCommissionNumber(304)
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->jointCommissionNumber(302)
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->jointCommissionNumber)->toBe(304)
        ->and($result[1]->jointCommissionNumber)->toBe(302);
});

it('separates periods by different social security numbers', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->employerEnterpriseNumber('0778603756')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->workerSocialSecurityNumber('95011426556')
            ->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->workerSocialSecurityNumber('96011426557')
            ->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 17:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(2)
        ->and($result[0]->workerSocialSecurityNumber)->toBe('95011426556')
        ->and($result[1]->workerSocialSecurityNumber)->toBe('96011426557');
});

it('merges employments across midnight when start date is same', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->employerEnterpriseNumber('0778603756')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi)
        ->workerSocialSecurityNumber('95011426556');

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt(CarbonImmutable::parse('2025-10-01 22:00'))
            ->endsAt(CarbonImmutable::parse('2025-10-01 23:59'))
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt(CarbonImmutable::parse('2025-10-01 23:59'))
            ->endsAt(CarbonImmutable::parse('2025-10-02 02:00'))
            ->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    // Note: Grouped by start date (Y-m-d), both start on 2025-10-01 so same group
    expect($result)->toHaveCount(1)
        ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
        ->and($result[0]->startsAt)->toEqual(CarbonImmutable::parse('2025-10-01 22:00'))
        ->and($result[0]->endsAt)->toEqual(CarbonImmutable::parse('2025-10-02 02:00'));
});

it('handles complex scenario with multiple groups and gaps', function () {
    $factory1 = EmploymentDataFactory::new()
        ->employerEnterpriseNumber('0778603756')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi)
        ->workerSocialSecurityNumber('95011426556');

    $factory2 = EmploymentDataFactory::new()
        ->employerEnterpriseNumber('0544881959')
        ->jointCommissionNumber(302)
        ->workerType(WorkerType::Student)
        ->workerSocialSecurityNumber('96011426557');

    $employments = new Collection([
        // Employer 1 - consecutive
        $factory1->id('e1-1')->startsAt(CarbonImmutable::parse('2025-10-01 07:00'))->endsAt(CarbonImmutable::parse('2025-10-01 12:00'))->create(),
        $factory1->id('e1-2')->startsAt(CarbonImmutable::parse('2025-10-01 12:00'))->endsAt(CarbonImmutable::parse('2025-10-01 15:00'))->create(),
        // Employer 1 - gap
        $factory1->id('e1-3')->startsAt(CarbonImmutable::parse('2025-10-01 16:00'))->endsAt(CarbonImmutable::parse('2025-10-01 20:00'))->create(),
        // Employer 2 - single
        $factory2->id('e2-1')->startsAt(CarbonImmutable::parse('2025-10-01 08:00'))->endsAt(CarbonImmutable::parse('2025-10-01 14:00'))->create(),
    ]);

    $result = ComputeDimonaPeriods::new()->execute($employments);

    expect($result)->toHaveCount(3)
        ->and($result[0]->employmentIds)->toBe(['e1-1', 'e1-2'])
        ->and($result[0]->employerEnterpriseNumber)->toBe('0778603756')
        ->and($result[1]->employmentIds)->toBe(['e1-3'])
        ->and($result[1]->employerEnterpriseNumber)->toBe('0778603756')
        ->and($result[2]->employmentIds)->toBe(['e2-1'])
        ->and($result[2]->employerEnterpriseNumber)->toBe('0544881959');
});
