<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Tests\Factories\EmploymentDataFactory;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->employerEnterpriseNumber = '0123456789';
    $this->workerSocialSecurityNumber = '12345678901';
});

describe('midnight-spanning periods', function () {
    it('handles periods spanning midnight correctly', function () {
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-01 23:00')
            ->endsAt('2025-10-02 01:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2025-10-01')
            ->and($periods[0]->startHour)->toBe('23:00')
            ->and($periods[0]->endDate)->toBe('2025-10-02')
            ->and($periods[0]->endHour)->toBe('01:00');
    });

    it('handles student workers with midnight-spanning period', function () {
        // Single student employment spanning midnight is treated as one period
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Student)
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-01 22:00')
            ->endsAt('2025-10-02 02:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        // Student workers with single employment spanning midnight create one period
        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2025-10-01')
            ->and($periods[0]->endDate)->toBe('2025-10-02')
            ->and($periods[0]->startHour)->toBeNull()
            ->and($periods[0]->endHour)->toBeNull()
            ->and($periods[0]->numberOfHours)->toBeFloat();
    });
});

describe('daylight saving time transitions', function () {
    it('handles spring DST transition correctly', function () {
        // In Belgium, clocks go forward on last Sunday of March at 2:00 AM to 3:00 AM
        // 2025-03-30 is the DST transition day
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2025-03-30 01:00')
            ->endsAt('2025-03-30 04:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2025-03-30')
            ->and($periods[0]->endDate)->toBe('2025-03-30');
    });

    it('handles fall DST transition correctly', function () {
        // In Belgium, clocks go back on last Sunday of October at 3:00 AM to 2:00 AM
        // 2025-10-26 is the DST transition day
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-26 01:00')
            ->endsAt('2025-10-26 04:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2025-10-26')
            ->and($periods[0]->endDate)->toBe('2025-10-26');
    });
});

describe('leap year dates', function () {
    it('handles February 29 on a leap year', function () {
        // 2024 is a leap year
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2024-02-29 08:00')
            ->endsAt('2024-02-29 17:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2024-02-29')
            ->and($periods[0]->endDate)->toBe('2024-02-29');
    });

    it('handles February to March spanning on a leap year', function () {
        // 2024 is a leap year
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Student)
            ->jointCommissionNumber(304)
            ->startsAt('2024-02-28 20:00')
            ->endsAt('2024-03-01 04:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        // Single employment creates one period, even spanning leap day
        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2024-02-28')
            ->and($periods[0]->endDate)->toBe('2024-03-01');
    });
});

describe('null time handling', function () {
    it('handles null start and end hours with number_of_hours specified', function () {
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Student)
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-01 00:00')
            ->endsAt('2025-10-01 00:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        // Student workers with same start/end time should use number_of_hours
        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2025-10-01')
            ->and($periods[0]->startHour)->toBeNull()
            ->and($periods[0]->endHour)->toBeNull()
            ->and($periods[0]->numberOfHours)->toBeFloat();
    });
});

describe('edge time values', function () {
    it('handles period starting at 00:00', function () {
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-01 00:00')
            ->endsAt('2025-10-01 08:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startHour)->toBe('00:00')
            ->and($periods[0]->endHour)->toBe('08:00');
    });

    it('handles period ending at 23:59', function () {
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-01 16:00')
            ->endsAt('2025-10-01 23:59')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startHour)->toBe('16:00')
            ->and($periods[0]->endHour)->toBe('23:59');
    });

    it('handles very short periods (1 minute)', function () {
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-01 12:00')
            ->endsAt('2025-10-01 12:01')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startHour)->toBe('12:00')
            ->and($periods[0]->endHour)->toBe('12:01');
    });
});

describe('multi-day periods', function () {
    it('handles period spanning multiple days', function () {
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-03 17:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2025-10-01')
            ->and($periods[0]->endDate)->toBe('2025-10-03');
    });

    it('handles student employment spanning multiple days', function () {
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Student)
            ->jointCommissionNumber(304)
            ->startsAt('2025-10-01 08:00')
            ->endsAt('2025-10-03 17:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        // Single student employment creates one period spanning multiple days
        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2025-10-01')
            ->and($periods[0]->endDate)->toBe('2025-10-03');
    });
});

describe('future and past dates', function () {
    it('handles periods far in the future', function () {
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2030-01-01 08:00')
            ->endsAt('2030-01-01 17:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2030-01-01');
    });

    it('handles historical dates', function () {
        $employment = EmploymentDataFactory::new()
            ->workerType(WorkerType::Flexi)
            ->jointCommissionNumber(304)
            ->startsAt('2020-01-01 08:00')
            ->endsAt('2020-01-01 17:00')
            ->create();

        $periods = ComputeExpectedDimonaPeriods::new()->execute(
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            employments: new Collection([$employment])
        );

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->startDate)->toBe('2020-01-01');
    });
});
