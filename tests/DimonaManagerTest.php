<?php

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Facades\Dimona;
use Hyperlab\Dimona\Jobs\SyncDimonaPeriods;
use Illuminate\Support\Facades\Queue;

it('can declare dimona for employments', function () {
    Queue::fake();

    $employerEnterpriseNumber = '0123456789';
    $workerSocialSecurityNumber = '12345678901';
    $period = CarbonPeriodImmutable::create(
        CarbonImmutable::parse('2025-10-01'),
        CarbonImmutable::parse('2025-10-07')
    );
    $employments = collect([
        new EmploymentData(
            id: 'employment-1',
            jointCommissionNumber: 200,
            workerType: WorkerType::Student,
            startsAt: CarbonImmutable::parse('2025-10-01 08:00'),
            endsAt: CarbonImmutable::parse('2025-10-01 17:00'),
            location: new EmploymentLocationData(
                name: 'Test Location',
                street: 'Test Street',
                houseNumber: '1',
                boxNumber: null,
                postalCode: '1000',
                place: 'Brussels',
                country: EmploymentLocationCountry::Belgium,
            ),
        ),
    ]);

    Dimona::declare($employerEnterpriseNumber, $workerSocialSecurityNumber, $period, $employments);

    Queue::assertPushed(SyncDimonaPeriods::class, function (SyncDimonaPeriods $job) use ($employerEnterpriseNumber, $workerSocialSecurityNumber, $period, $employments) {
        return $job->employerEnterpriseNumber === $employerEnterpriseNumber
            && $job->workerSocialSecurityNumber === $workerSocialSecurityNumber
            && $job->period->equalTo($period)
            && $job->employments->count() === $employments->count()
            && $job->clientId === null;
    });
});

it('can declare dimona with a specific client', function () {
    Queue::fake();

    $employerEnterpriseNumber = '0123456789';
    $workerSocialSecurityNumber = '12345678901';
    $clientId = 'test-client';
    $period = CarbonPeriodImmutable::create(
        CarbonImmutable::parse('2025-10-01'),
        CarbonImmutable::parse('2025-10-07')
    );
    $employments = collect([
        new EmploymentData(
            id: 'employment-1',
            jointCommissionNumber: 200,
            workerType: WorkerType::Student,
            startsAt: CarbonImmutable::parse('2025-10-01 08:00'),
            endsAt: CarbonImmutable::parse('2025-10-01 17:00'),
            location: new EmploymentLocationData(
                name: 'Test Location',
                street: 'Test Street',
                houseNumber: '1',
                boxNumber: null,
                postalCode: '1000',
                place: 'Brussels',
                country: EmploymentLocationCountry::Belgium,
            ),
        ),
    ]);

    Dimona::client($clientId)->declare($employerEnterpriseNumber, $workerSocialSecurityNumber, $period, $employments);

    Queue::assertPushed(SyncDimonaPeriods::class, function (SyncDimonaPeriods $job) use ($employerEnterpriseNumber, $workerSocialSecurityNumber, $period, $employments, $clientId) {
        return $job->employerEnterpriseNumber === $employerEnterpriseNumber
            && $job->workerSocialSecurityNumber === $workerSocialSecurityNumber
            && $job->period->equalTo($period)
            && $job->employments->count() === $employments->count()
            && $job->clientId === $clientId;
    });
});
