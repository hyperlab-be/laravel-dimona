<?php

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaWorkerTypeException;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

require_once __DIR__.'/Helpers.php';

function occasionalEmployment(string $id, string $startsAt, string $endsAt, int $jointCommissionNumber = 302): EmploymentData
{
    return EmploymentDataFactory::new()
        ->id($id)
        ->jointCommissionNumber($jointCommissionNumber)
        ->workerType(WorkerType::Occasional)
        ->startsAt($startsAt)
        ->endsAt($endsAt)
        ->create();
}

/**
 * Occasional employments from 18:00 to 23:00 on each of the given days.
 */
function occasionalEmploymentsOnDays(array $days): Collection
{
    return collect($days)->map(fn (string $day) => occasionalEmployment("employment-{$day}", "{$day} 18:00", "{$day} 23:00"));
}

describe('worker type fallback', function () {

    beforeEach(function () {
        DimonaWorkerTypeException::query()->create([
            'social_security_number' => WORKER_SSN,
            'worker_type' => WorkerType::Flexi,
            'starts_at' => CarbonImmutable::parse('2025-10-01')->startOfQuarter(),
            'ends_at' => CarbonImmutable::parse('2025-10-01')->endOfQuarter(),
        ]);
    });

    it('declares a flexi worker within an exception as occasional when the joint commission is configured', function () {
        config()->set('dimona.occasional_joint_commissions', [302]);

        $result = computeExpectedDimonaPeriods(new Collection([
            EmploymentDataFactory::new()
                ->id('employment-1')
                ->jointCommissionNumber(302)
                ->workerType(WorkerType::Flexi)
                ->startsAt('2025-10-01 18:00')
                ->endsAt('2025-10-01 23:00')
                ->create(),
        ]));

        expect($result)->toHaveCount(1)
            ->and($result[0]->workerType)->toBe(WorkerType::Occasional)
            ->and($result[0]->startHour)->toBe('18:00')
            ->and($result[0]->endHour)->toBe('23:00');
    });

    it('declares a flexi worker within an exception as other with the default configuration', function () {
        $result = computeExpectedDimonaPeriods(new Collection([
            EmploymentDataFactory::new()
                ->id('employment-1')
                ->jointCommissionNumber(302)
                ->workerType(WorkerType::Flexi)
                ->startsAt('2025-10-01 18:00')
                ->endsAt('2025-10-01 23:00')
                ->create(),
        ]));

        expect($result)->toHaveCount(1)
            ->and($result[0]->workerType)->toBe(WorkerType::Other);
    });

    it('keeps declaring other per day in a joint commission that is not configured', function () {
        config()->set('dimona.occasional_joint_commissions', [302]);

        $employments = collect(['2025-10-01', '2025-10-02', '2025-10-03'])->map(
            fn (string $day) => EmploymentDataFactory::new()
                ->id("employment-{$day}")
                ->jointCommissionNumber(304)
                ->workerType(WorkerType::Flexi)
                ->startsAt("{$day} 18:00")
                ->endsAt("{$day} 23:00")
                ->create()
        );

        $result = computeExpectedDimonaPeriods($employments);

        expect($result)->toHaveCount(3)
            ->and($result->pluck('workerType')->unique()->all())->toBe([WorkerType::Other])
            ->and($result->pluck('startDate')->all())->toBe(['2025-10-01', '2025-10-02', '2025-10-03'])
            ->and($result->pluck('employmentIds')->all())->toBe([
                ['employment-2025-10-01'],
                ['employment-2025-10-02'],
                ['employment-2025-10-03'],
            ]);
    });

});

describe('occasional period per day', function () {

    it('declares the start and end hour', function () {
        $result = computeExpectedDimonaPeriods(new Collection([
            occasionalEmployment('employment-1', '2025-10-01 10:00', '2025-10-01 14:30'),
        ]));

        expect($result)->toHaveCount(1)
            ->and($result[0]->employmentIds)->toBe(['employment-1'])
            ->and($result[0]->jointCommissionNumber)->toBe(302)
            ->and($result[0]->workerType)->toBe(WorkerType::Occasional)
            ->and($result[0]->startDate)->toBe('2025-10-01')
            ->and($result[0]->startHour)->toBe('10:00')
            ->and($result[0]->endDate)->toBe('2025-10-01')
            ->and($result[0]->endHour)->toBe('14:30')
            ->and($result[0]->numberOfHours)->toBeNull();
    });

    it('declares the actual end date when the shift runs past midnight', function () {
        $result = computeExpectedDimonaPeriods(new Collection([
            occasionalEmployment('employment-1', '2025-10-01 20:00', '2025-10-02 03:00'),
        ]));

        expect($result)->toHaveCount(1)
            ->and($result[0]->startDate)->toBe('2025-10-01')
            ->and($result[0]->startHour)->toBe('20:00')
            ->and($result[0]->endDate)->toBe('2025-10-02')
            ->and($result[0]->endHour)->toBe('03:00');
    });

    it('declares a single period from the earliest start to the latest end for shifts on the same day', function () {
        $result = computeExpectedDimonaPeriods(new Collection([
            occasionalEmployment('employment-2', '2025-10-01 18:00', '2025-10-01 23:00'),
            occasionalEmployment('employment-1', '2025-10-01 08:00', '2025-10-01 12:00'),
        ]));

        expect($result)->toHaveCount(1)
            ->and($result[0]->employmentIds)->toBe(['employment-1', 'employment-2'])
            ->and($result[0]->startHour)->toBe('08:00')
            ->and($result[0]->endDate)->toBe('2025-10-01')
            ->and($result[0]->endHour)->toBe('23:00');
    });

    it('uses the date in Brussels', function () {
        $result = computeExpectedDimonaPeriods(new Collection([
            EmploymentDataFactory::new()
                ->id('employment-1')
                ->jointCommissionNumber(302)
                ->workerType(WorkerType::Occasional)
                ->startsAt(CarbonImmutable::parse('2025-09-30 22:30', 'UTC'))
                ->endsAt(CarbonImmutable::parse('2025-10-01 04:00', 'UTC'))
                ->create(),
        ]));

        expect($result[0]->startDate)->toBe('2025-10-01')
            ->and($result[0]->startHour)->toBe('00:30')
            ->and($result[0]->endHour)->toBe('06:00');
    });

});

describe('series of consecutive days', function () {

    it('declares a single day as occasional', function () {
        $result = computeExpectedDimonaPeriods(occasionalEmploymentsOnDays(['2025-10-01']));

        expect($result)->toHaveCount(1)
            ->and($result[0]->workerType)->toBe(WorkerType::Occasional);
    });

    it('declares two consecutive days as occasional per day', function () {
        $result = computeExpectedDimonaPeriods(occasionalEmploymentsOnDays(['2025-10-01', '2025-10-02']));

        expect($result)->toHaveCount(2)
            ->and($result->pluck('workerType')->unique()->all())->toBe([WorkerType::Occasional])
            ->and($result->pluck('startDate')->all())->toBe(['2025-10-01', '2025-10-02'])
            ->and($result->pluck('employmentIds')->all())->toBe([['employment-2025-10-01'], ['employment-2025-10-02']]);
    });

    it('declares three consecutive days as a single other period', function () {
        $result = computeExpectedDimonaPeriods(occasionalEmploymentsOnDays(['2025-10-01', '2025-10-02', '2025-10-03']));

        expect($result)->toHaveCount(1)
            ->and($result[0]->workerType)->toBe(WorkerType::Other)
            ->and($result[0]->jointCommissionNumber)->toBe(302)
            ->and($result[0]->startDate)->toBe('2025-10-01')
            ->and($result[0]->startHour)->toBeNull()
            ->and($result[0]->endDate)->toBe('2025-10-03')
            ->and($result[0]->endHour)->toBeNull()
            ->and($result[0]->numberOfHours)->toBeNull()
            ->and($result[0]->employmentIds)->toBe(['employment-2025-10-01', 'employment-2025-10-02', 'employment-2025-10-03']);
    });

    it('declares five consecutive days as a single other period', function () {
        $result = computeExpectedDimonaPeriods(occasionalEmploymentsOnDays([
            '2025-10-05', '2025-10-01', '2025-10-03', '2025-10-02', '2025-10-04',
        ]));

        expect($result)->toHaveCount(1)
            ->and($result[0]->workerType)->toBe(WorkerType::Other)
            ->and($result[0]->startDate)->toBe('2025-10-01')
            ->and($result[0]->endDate)->toBe('2025-10-05')
            ->and($result[0]->employmentIds)->toHaveCount(5);
    });

    it('splits series on a day without work', function () {
        $result = computeExpectedDimonaPeriods(occasionalEmploymentsOnDays([
            '2025-10-01', '2025-10-02', '2025-10-03',
            '2025-10-05', '2025-10-06',
        ]));

        expect($result)->toHaveCount(3)
            ->and($result[0]->workerType)->toBe(WorkerType::Other)
            ->and($result[0]->startDate)->toBe('2025-10-01')
            ->and($result[0]->endDate)->toBe('2025-10-03')
            ->and($result[1]->workerType)->toBe(WorkerType::Occasional)
            ->and($result[1]->startDate)->toBe('2025-10-05')
            ->and($result[2]->workerType)->toBe(WorkerType::Occasional)
            ->and($result[2]->startDate)->toBe('2025-10-06');
    });

    it('counts a night shift as the day it starts on', function () {
        $result = computeExpectedDimonaPeriods(new Collection([
            occasionalEmployment('employment-1', '2025-10-01 22:00', '2025-10-02 04:00'),
            occasionalEmployment('employment-2', '2025-10-02 22:00', '2025-10-03 04:00'),
        ]));

        expect($result)->toHaveCount(2)
            ->and($result->pluck('workerType')->unique()->all())->toBe([WorkerType::Occasional])
            ->and($result->pluck('startDate')->all())->toBe(['2025-10-01', '2025-10-02'])
            ->and($result->pluck('endDate')->all())->toBe(['2025-10-02', '2025-10-03']);
    });

    it('ends the other period on the end date of the last employment', function () {
        $result = computeExpectedDimonaPeriods(new Collection([
            occasionalEmployment('employment-1', '2025-10-01 18:00', '2025-10-01 23:00'),
            occasionalEmployment('employment-2', '2025-10-02 18:00', '2025-10-02 23:00'),
            occasionalEmployment('employment-3', '2025-10-03 22:00', '2025-10-04 04:00'),
        ]));

        expect($result)->toHaveCount(1)
            ->and($result[0]->startDate)->toBe('2025-10-01')
            ->and($result[0]->endDate)->toBe('2025-10-04');
    });

    it('builds series per joint commission', function () {
        $result = computeExpectedDimonaPeriods(new Collection([
            occasionalEmployment('employment-1', '2025-10-01 18:00', '2025-10-01 23:00', 302),
            occasionalEmployment('employment-2', '2025-10-02 18:00', '2025-10-02 23:00', 304),
            occasionalEmployment('employment-3', '2025-10-03 18:00', '2025-10-03 23:00', 302),
        ]));

        expect($result)->toHaveCount(3)
            ->and($result->pluck('workerType')->unique()->all())->toBe([WorkerType::Occasional]);
    });

    it('keeps other worker types per day next to a series', function () {
        $result = computeExpectedDimonaPeriods(occasionalEmploymentsOnDays(['2025-10-01', '2025-10-02', '2025-10-03'])->push(
            EmploymentDataFactory::new()
                ->id('flexi-employment')
                ->jointCommissionNumber(302)
                ->workerType(WorkerType::Flexi)
                ->startsAt('2025-10-02 08:00')
                ->endsAt('2025-10-02 12:00')
                ->create()
        ));

        expect($result)->toHaveCount(2)
            ->and($result[0]->workerType)->toBe(WorkerType::Flexi)
            ->and($result[0]->employmentIds)->toBe(['flexi-employment'])
            ->and($result[1]->workerType)->toBe(WorkerType::Other)
            ->and($result[1]->employmentIds)->toHaveCount(3);
    });

});
