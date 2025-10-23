<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

it('populates all dimona period data fields correctly for flexi worker', function () {
    $location = new EmploymentLocationData(
        name: 'Main Office',
        street: 'Main Street',
        houseNumber: '123',
        boxNumber: null,
        postalCode: '1000',
        place: 'Brussels',
        country: EmploymentLocationCountry::Belgium
    );

    $employment = EmploymentDataFactory::new()
        ->id('emp-1')
        ->jointCommissionNumber(304)
        ->workerType(WorkerType::Flexi)
        ->startsAt('2025-10-01 14:30')
        ->endsAt('2025-10-01 18:45')
        ->location($location)
        ->create();

    $result = computeExpectedDimonaPeriods(new Collection([$employment]));

    $period = $result[0];
    expect($period->employmentIds)->toBe(['emp-1'])
        ->and($period->employerEnterpriseNumber)->toBe(EMPLOYER_ENTERPRISE_NUMBER)
        ->and($period->workerSocialSecurityNumber)->toBe(WORKER_SSN)
        ->and($period->jointCommissionNumber)->toBe(304)
        ->and($period->workerType)->toBe(WorkerType::Flexi)
        ->and($period->startDate)->toBe('2025-10-01')
        ->and($period->startHour)->toBe('14:30')
        ->and($period->endDate)->toBe('2025-10-01')
        ->and($period->endHour)->toBe('18:45')
        ->and($period->numberOfHours)->toBeNull()
        ->and($period->location)->toBe($location);
});

it('populates all dimona period data fields correctly for student worker', function () {
    $location = new EmploymentLocationData(
        name: 'Student Campus',
        street: 'Student Lane',
        houseNumber: '456',
        boxNumber: 'A',
        postalCode: '2000',
        place: 'Antwerp',
        country: EmploymentLocationCountry::Belgium
    );

    $employment = EmploymentDataFactory::new()
        ->id('emp-2')
        ->jointCommissionNumber(302)
        ->workerType(WorkerType::Student)
        ->startsAt('2025-10-01 09:00')
        ->endsAt('2025-10-01 15:30')
        ->location($location)
        ->create();

    $result = computeExpectedDimonaPeriods(new Collection([$employment]));

    $period = $result[0];
    expect($period->employmentIds)->toBe(['emp-2'])
        ->and($period->employerEnterpriseNumber)->toBe(EMPLOYER_ENTERPRISE_NUMBER)
        ->and($period->workerSocialSecurityNumber)->toBe(WORKER_SSN)
        ->and($period->jointCommissionNumber)->toBe(302)
        ->and($period->workerType)->toBe(WorkerType::Student)
        ->and($period->startDate)->toBe('2025-10-01')
        ->and($period->startHour)->toBeNull()
        ->and($period->endDate)->toBe('2025-10-01')
        ->and($period->endHour)->toBeNull()
        ->and($period->numberOfHours)->toBe(6.5)
        ->and($period->location)->toBe($location);
});

it('converts UTC timezone to Europe/Brussels correctly', function () {
    $employment = EmploymentDataFactory::new()
        ->id('emp-1')
        ->workerType(WorkerType::Flexi)
        ->startsAt(CarbonImmutable::parse('2025-10-01 12:00:00', 'UTC')) // Noon UTC
        ->endsAt(CarbonImmutable::parse('2025-10-01 16:00:00', 'UTC'))   // 4 PM UTC
        ->create();

    $result = computeExpectedDimonaPeriods(new Collection([$employment]));

    $period = $result[0];
    // UTC+2 in October (CEST): 12:00 UTC = 14:00 Brussels, 16:00 UTC = 18:00 Brussels
    expect($period->startDate)->toBe('2025-10-01')
        ->and($period->startHour)->toBe('14:00')
        ->and($period->endDate)->toBe('2025-10-01')
        ->and($period->endHour)->toBe('18:00');
});

it('handles employment spanning multiple days', function () {
    $employment = EmploymentDataFactory::new()
        ->id('emp-1')
        ->workerType(WorkerType::Flexi)
        ->startsAt('2025-10-01 22:00')
        ->endsAt('2025-10-02 06:00')
        ->create();

    $result = computeExpectedDimonaPeriods(new Collection([$employment]));

    $period = $result[0];
    expect($period->startDate)->toBe('2025-10-01')
        ->and($period->startHour)->toBe('22:00')
        ->and($period->endDate)->toBe('2025-10-02')
        ->and($period->endHour)->toBe('06:00');
});
