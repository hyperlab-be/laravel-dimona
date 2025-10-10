<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Services\WorkerTypeExceptionService;
use Illuminate\Support\Collection;

class ComputeDimonaPeriods
{
    private const string TIMEZONE = 'Europe/Brussels';

    private string $employerEnterpriseNumber;
    private string $workerSocialSecurityNumber;

    public function __construct(
        private readonly WorkerTypeExceptionService $workerTypeExceptionService
    ) {}

    public static function new(): static
    {
        return app(static::class);
    }

    /**
     * @param  Collection<EmploymentData>  $employments
     */
    public function execute(
        string $employerEnterpriseNumber,
        string $workerSocialSecurityNumber,
        Collection $employments
    ): Collection {
        $this->employerEnterpriseNumber = $employerEnterpriseNumber;
        $this->workerSocialSecurityNumber = $workerSocialSecurityNumber;

        return $employments
            ->each(fn (EmploymentData $employment) => $this->resolveWorkerType($employment))
            ->groupBy(fn (EmploymentData $employment) => $this->generateGroupingKey($employment))
            ->flatMap(fn (Collection $employments) => $this->createDimonaPeriods($employments));
    }

    private function resolveWorkerType(EmploymentData $employment): void
    {
        $employment->workerType = $this->workerTypeExceptionService->resolveWorkerType(
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            workerType: $employment->workerType,
            employmentStartsAt: $employment->startsAt,
        );
    }

    private function generateGroupingKey(EmploymentData $employment): string
    {
        return json_encode([
            $employment->jointCommissionNumber,
            $employment->workerType->value,
            $this->formatDate($employment->startsAt),
        ]);
    }

    private function createDimonaPeriods(Collection $employments): Collection
    {
        return $employments
            ->sortBy('startsAt')
            ->reduce(
                function (Collection $dimonaPeriods, EmploymentData $employment) {
                    match ($employment->workerType) {
                        WorkerType::Flexi => $this->createOrUpdateFlexiPeriod($dimonaPeriods, $employment),
                        WorkerType::Student => $this->createOrUpdateStudentPeriod($dimonaPeriods, $employment),
                        WorkerType::Other => $this->createOrUpdateOtherPeriod($dimonaPeriods, $employment),
                    };

                    return $dimonaPeriods;
                },
                new Collection
            );
    }

    private function createOrUpdateFlexiPeriod(Collection $dimonaPeriods, EmploymentData $employment): void
    {
        $dimonaPeriods->push(new DimonaPeriodData(
            employmentIds: [$employment->id],
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            jointCommissionNumber: $employment->jointCommissionNumber,
            workerType: $employment->workerType,
            startDate: $this->formatDate($employment->startsAt),
            startHour: $this->formatHour($employment->startsAt),
            endDate: $this->formatDate($employment->endsAt),
            endHour: $this->formatHour($employment->endsAt),
            numberOfHours: null,
            location: $employment->location,
        ));
    }

    private function createOrUpdateStudentPeriod(Collection $dimonaPeriods, EmploymentData $employment): void
    {
        $numberOfHours = $employment->startsAt->diffInHours($employment->endsAt, true);
        /** @var DimonaPeriodData|null $lastDimonaPeriod */
        $lastDimonaPeriod = $dimonaPeriods->last();

        if ($lastDimonaPeriod) {
            $lastDimonaPeriod->employmentIds[] = $employment->id;
            $lastDimonaPeriod->numberOfHours += $numberOfHours;
        } else {
            $dimonaPeriods->push(new DimonaPeriodData(
                employmentIds: [$employment->id],
                employerEnterpriseNumber: $this->employerEnterpriseNumber,
                workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
                jointCommissionNumber: $employment->jointCommissionNumber,
                workerType: $employment->workerType,
                startDate: $this->formatDate($employment->startsAt),
                startHour: null,
                endDate: $this->formatDate($employment->endsAt),
                endHour: null,
                numberOfHours: $numberOfHours,
                location: $employment->location,
            ));
        }
    }

    private function createOrUpdateOtherPeriod(Collection $dimonaPeriods, EmploymentData $employment): void
    {
        /** @var DimonaPeriodData|null $lastDimonaPeriod */
        $lastDimonaPeriod = $dimonaPeriods->last();

        if ($lastDimonaPeriod) {
            $lastDimonaPeriod->employmentIds[] = $employment->id;
        } else {
            $dimonaPeriods->push(new DimonaPeriodData(
                employmentIds: [$employment->id],
                employerEnterpriseNumber: $this->employerEnterpriseNumber,
                workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
                jointCommissionNumber: $employment->jointCommissionNumber,
                workerType: $employment->workerType,
                startDate: $this->formatDate($employment->startsAt),
                startHour: null,
                endDate: $this->formatDate($employment->endsAt),
                endHour: null,
                numberOfHours: null,
                location: $employment->location,
            ));
        }
    }

    private function formatDate(CarbonImmutable $date): string
    {
        return $date->setTimezone(self::TIMEZONE)->format('Y-m-d');
    }

    private function formatHour(CarbonImmutable $date): string
    {
        return $date->setTimezone(self::TIMEZONE)->format('Hi');
    }
}
