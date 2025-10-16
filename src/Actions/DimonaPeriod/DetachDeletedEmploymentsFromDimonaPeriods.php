<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DetachDeletedEmploymentsFromDimonaPeriods
{
    public static function new(): self
    {
        return new self;
    }

    /**
     * Remove deleted employment IDs from actual dimona periods.
     *
     * @param  Collection<EmploymentData>  $employments
     */
    public function execute(
        string $employerEnterpriseNumber, string $workerSocialSecurityNumber, CarbonPeriodImmutable $period,
        Collection $employments
    ): void {
        DB::table('dimona_period_employment')
            ->whereNotIn('employment_id', $employments->pluck('id')->toArray())
            ->whereIn('dimona_period_id', function ($query) use ($employerEnterpriseNumber, $workerSocialSecurityNumber, $period) {
                $query
                    ->select('id')
                    ->from('dimona_periods')
                    ->where('employer_enterprise_number', $employerEnterpriseNumber)
                    ->where('worker_social_security_number', $workerSocialSecurityNumber)
                    ->whereBetween('start_date', [$period->start->format('Y-m-d'), $period->end->format('Y-m-d')])
                    ->whereNotIn('state', [DimonaPeriodState::Cancelled, DimonaPeriodState::Failed]);
            })
            ->delete();
    }
}
