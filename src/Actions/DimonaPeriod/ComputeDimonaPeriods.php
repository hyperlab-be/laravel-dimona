<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Data\EmploymentData;
use Illuminate\Support\Collection;

class ComputeDimonaPeriods
{
    public static function new(): static
    {
        return app(static::class);
    }

    /**
     * @param  Collection<EmploymentData>  $employments
     */
    public function execute(Collection $employments): Collection
    {
        return $employments
            ->groupBy(function (EmploymentData $employment) {
                return json_encode([
                    $employment->employerEnterpriseNumber,
                    $employment->jointCommissionNumber,
                    $employment->workerType,
                    $employment->workerSocialSecurityNumber,
                    $employment->startsAt->format('Y-m-d'),
                ]);
            })
            ->flatMap(function (Collection $employments) {
                return $employments
                    ->sortBy('startsAt')
                    ->reduce(
                        function (Collection $dimonaPeriods, EmploymentData $employment) {
                            /** @var DimonaPeriodData|null $lastDimonaPeriod */
                            $lastDimonaPeriod = $dimonaPeriods->last();

                            if ($lastDimonaPeriod?->endsAt->eq($employment->startsAt)) {
                                $lastDimonaPeriod->employmentIds[] = $employment->id;
                                $lastDimonaPeriod->endsAt = $employment->endsAt;
                            } else {
                                $dimonaPeriods->push(new DimonaPeriodData(
                                    employmentIds: [$employment->id],
                                    employerEnterpriseNumber: $employment->employerEnterpriseNumber,
                                    jointCommissionNumber: $employment->jointCommissionNumber,
                                    workerType: $employment->workerType,
                                    workerSocialSecurityNumber: $employment->workerSocialSecurityNumber,
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
