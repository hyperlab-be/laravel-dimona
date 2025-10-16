<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

it('creates separate periods for multiple flexi employments', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 07:00')
            ->endsAt('2025-10-01 12:00')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 15:00')
            ->create(),
        $baseFactory
            ->id('employment-3')
            ->startsAt('2025-10-01 15:00')
            ->endsAt('2025-10-01 18:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(3)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2'])
        ->and($result[2]->employmentIds)->toBe(['employment-3']);
});

it('creates separate flexi periods even across midnight when start date is same', function () {
    $baseFactory = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $employments = new Collection([
        $baseFactory
            ->id('employment-1')
            ->startsAt('2025-10-01 22:00')
            ->endsAt('2025-10-01 23:59')
            ->create(),
        $baseFactory
            ->id('employment-2')
            ->startsAt('2025-10-01 23:59')
            ->endsAt('2025-10-02 02:00')
            ->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    // Note: Grouped by start date (Y-m-d), both start on 2025-10-01 so same group
    // But Flexi workers create separate periods for each employment
    expect($result)->toHaveCount(2)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2']);
});

it('handles complex scenario with multiple groups and gaps', function () {
    $factory1 = EmploymentDataFactory::new()
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi);

    $factory2 = EmploymentDataFactory::new()
        ->jointCommissionNumber(302)
        ->workerType(WorkerType::Student);

    $employments = new Collection([
        // Flexi workers - each creates separate period
        $factory1->id('e1-1')->startsAt('2025-10-01 07:00')->endsAt('2025-10-01 12:00')->create(),
        $factory1->id('e1-2')->startsAt('2025-10-01 12:00')->endsAt('2025-10-01 15:00')->create(),
        $factory1->id('e1-3')->startsAt('2025-10-01 16:00')->endsAt('2025-10-01 20:00')->create(),
        // Student - single
        $factory2->id('e2-1')->startsAt('2025-10-01 08:00')->endsAt('2025-10-01 14:00')->create(),
    ]);

    $result = ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);

    expect($result)->toHaveCount(4)
        ->and($result[0]->employmentIds)->toBe(['e1-1'])
        ->and($result[1]->employmentIds)->toBe(['e1-2'])
        ->and($result[2]->employmentIds)->toBe(['e1-3'])
        ->and($result[3]->employmentIds)->toBe(['e2-1']);
});
