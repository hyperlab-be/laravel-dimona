<?php

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

    $result = computeExpectedDimonaPeriods($employments);

    expect($result)->toHaveCount(3)
        ->and($result[0]->employmentIds)->toBe(['employment-1'])
        ->and($result[1]->employmentIds)->toBe(['employment-2'])
        ->and($result[2]->employmentIds)->toBe(['employment-3']);
});
