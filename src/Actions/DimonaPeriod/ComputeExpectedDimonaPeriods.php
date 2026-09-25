<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Services\WorkerTypeExceptionService;
use Illuminate\Support\Collection;
use LogicException;

class ComputeExpectedDimonaPeriods
{
    private const string TIMEZONE = 'Europe/Brussels';

    /**
     * An occasional worker can be declared for at most this many consecutive days,
     * a longer series is declared as a single Other period.
     */
    private const int MAX_CONSECUTIVE_OCCASIONAL_DAYS = 2;

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

        [$occasionalEmployments, $employments] = $employments
            ->each(fn (EmploymentData $employment) => $this->resolveWorkerType($employment))
            ->partition(fn (EmploymentData $employment) => $employment->workerType === WorkerType::Occasional);

        return $employments
            ->groupBy(fn (EmploymentData $employment) => $this->generateGroupingKey($employment))
            ->flatMap(fn (Collection $employments) => $this->createDimonaPeriods($employments))
            ->merge($this->createOccasionalDimonaPeriods($occasionalEmployments))
            ->values();
    }

    private function resolveWorkerType(EmploymentData $employment): void
    {
        $employment->workerType = $this->workerTypeExceptionService->resolveWorkerType(
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            workerType: $employment->workerType,
            employmentStartsAt: $employment->startsAt,
            jointCommissionNumber: $employment->jointCommissionNumber,
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
                        WorkerType::Occasional => throw new LogicException('Occasional employments are grouped per series of consecutive days.'),
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

    /**
     * Occasional employments are grouped per joint commission into series of consecutive days.
     * A short series is declared per day, a longer one as a single Other period.
     */
    private function createOccasionalDimonaPeriods(Collection $employments): Collection
    {
        return $employments
            ->groupBy(fn (EmploymentData $employment) => $employment->jointCommissionNumber)
            ->flatMap(fn (Collection $employments) => $this->groupIntoConsecutiveDays($employments))
            ->flatMap(fn (Collection $days) => $days->count() > self::MAX_CONSECUTIVE_OCCASIONAL_DAYS
                ? [$this->createOtherPeriodForConsecutiveDays($days)]
                : $days->map(fn (Collection $employments) => $this->createOccasionalPeriod($employments))->values()
            );
    }

    /**
     * @return Collection<Collection<string, Collection<EmploymentData>>> series of consecutive days, keyed by date,
     *                                                                    with the employments of each day sorted by start
     */
    private function groupIntoConsecutiveDays(Collection $employments): Collection
    {
        return $employments
            ->sortBy('startsAt')
            ->groupBy(fn (EmploymentData $employment) => $this->formatDate($employment->startsAt))
            ->reduce(
                function (Collection $series, Collection $employments, string $date) {
                    /** @var Collection|null $lastSeries */
                    $lastSeries = $series->last();
                    $lastDate = $lastSeries?->keys()->last();

                    if ($lastDate && CarbonImmutable::parse($lastDate)->addDay()->format('Y-m-d') === $date) {
                        $lastSeries->put($date, $employments);
                    } else {
                        $series->push(new Collection([$date => $employments]));
                    }

                    return $series;
                },
                new Collection
            );
    }

    /**
     * A single occasional period for all employments on one day, from the earliest start to the latest end.
     */
    private function createOccasionalPeriod(Collection $employments): DimonaPeriodData
    {
        /** @var EmploymentData $firstEmployment */
        $firstEmployment = $employments->first();
        $endsAt = $employments->max('endsAt');

        return new DimonaPeriodData(
            employmentIds: $employments->pluck('id')->all(),
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            jointCommissionNumber: $firstEmployment->jointCommissionNumber,
            workerType: WorkerType::Occasional,
            startDate: $this->formatDate($firstEmployment->startsAt),
            startHour: $this->formatHour($firstEmployment->startsAt),
            endDate: $this->formatDate($endsAt),
            endHour: $this->formatHour($endsAt),
            numberOfHours: null,
            location: $firstEmployment->location,
        );
    }

    /**
     * A single Other period from the first to the last day of the series.
     */
    private function createOtherPeriodForConsecutiveDays(Collection $days): DimonaPeriodData
    {
        $employments = $days->flatten(1);

        /** @var EmploymentData $firstEmployment */
        $firstEmployment = $employments->first();

        return new DimonaPeriodData(
            employmentIds: $employments->pluck('id')->all(),
            employerEnterpriseNumber: $this->employerEnterpriseNumber,
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber,
            jointCommissionNumber: $firstEmployment->jointCommissionNumber,
            workerType: WorkerType::Other,
            startDate: $this->formatDate($firstEmployment->startsAt),
            startHour: null,
            endDate: $this->formatDate($employments->max('endsAt')),
            endHour: null,
            numberOfHours: null,
            location: $firstEmployment->location,
        );
    }

    private function formatDate(CarbonImmutable $date): string
    {
        return $date->setTimezone(self::TIMEZONE)->format('Y-m-d');
    }

    private function formatHour(CarbonImmutable $date): string
    {
        return $date->setTimezone(self::TIMEZONE)->format('H:i');
    }
}
