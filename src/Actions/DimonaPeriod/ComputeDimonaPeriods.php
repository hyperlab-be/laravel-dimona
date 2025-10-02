<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Services\WorkerTypeExceptionService;
use Illuminate\Support\Collection;

use function json_encode;

class ComputeDimonaPeriods
{
    public function __construct(
        private WorkerTypeExceptionService $workerTypeExceptionService
    ) {}

    public static function new(): static
    {
        return app(static::class);
    }

    /**
     * @param  Collection<EmploymentData>  $employments
     */
    public function execute(
        string $employerEnterpriseNumber, string $workerSocialSecurityNumber, Collection $employments
    ): Collection {
        return $employments
            ->each(function (EmploymentData $employment) use ($workerSocialSecurityNumber) {
                $employment->workerType = $this->workerTypeExceptionService->resolveWorkerType(
                    workerSocialSecurityNumber: $workerSocialSecurityNumber,
                    workerType: $employment->workerType,
                    employmentStartsAt: $employment->startsAt,
                );
            })
            ->groupBy(function (EmploymentData $employment) {
                return json_encode([
                    $employment->jointCommissionNumber,
                    $employment->workerType->value,
                    $employment->startsAt->format('Y-m-d'),
                ]);
            })
            ->map(function (Collection $employments) use ($employerEnterpriseNumber, $workerSocialSecurityNumber) {
                return $employments
                    ->sortBy('startsAt')
                    ->reduce(
                        function (Collection $dimonaPeriods, EmploymentData $employment) use ($employerEnterpriseNumber, $workerSocialSecurityNumber) {
                            /** @var DimonaPeriodData|null $lastDimonaPeriod */
                            $lastDimonaPeriod = $dimonaPeriods->last();

                            if ($lastDimonaPeriod?->endsAt->eq($employment->startsAt)) {
                                $lastDimonaPeriod->employmentIds[] = $employment->id;
                                $lastDimonaPeriod->endsAt = $employment->endsAt;
                            } else {
                                $dimonaPeriods->push(new DimonaPeriodData(
                                    employmentIds: [$employment->id],
                                    employerEnterpriseNumber: $employerEnterpriseNumber,
                                    workerSocialSecurityNumber: $workerSocialSecurityNumber,
                                    jointCommissionNumber: $employment->jointCommissionNumber,
                                    workerType: $employment->workerType,
                                    startsAt: $employment->startsAt,
                                    endsAt: $employment->endsAt,
                                    location: $employment->location,
                                ));
                            }

                            return $dimonaPeriods;
                        },
                        new Collection
                    );
            })
            ->values();
    }
}
