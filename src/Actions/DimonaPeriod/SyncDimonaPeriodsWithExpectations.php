<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Events\DimonaPeriodCreated;
use Hyperlab\Dimona\Events\DimonaPeriodUpdated;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class SyncDimonaPeriodsWithExpectations
{
    private string $employerEnterpriseNumber;

    private string $workerSocialSecurityNumber;

    private CarbonPeriodImmutable $period;

    public static function new(): self
    {
        return new self;
    }

    /**
     * Link expected dimona periods to actual dimona periods.
     *
     * @param  Collection<DimonaPeriodData>  $expectedDimonaPeriods
     */
    public function execute(
        string $employerEnterpriseNumber,
        string $workerSocialSecurityNumber,
        CarbonPeriodImmutable $period,
        Collection $expectedDimonaPeriods
    ): void {
        $this->employerEnterpriseNumber = $employerEnterpriseNumber;
        $this->workerSocialSecurityNumber = $workerSocialSecurityNumber;
        $this->period = $period;

        $this->detachDeletedEmploymentsFromDimonaPeriods($expectedDimonaPeriods);

        $expectedDimonaPeriods->each(fn (DimonaPeriodData $data) => $this->syncPeriod($data));
    }

    private function detachDeletedEmploymentsFromDimonaPeriods(Collection $expectedDimonaPeriods): void
    {
        $employmentIds = $expectedDimonaPeriods
            ->flatMap(fn (DimonaPeriodData $data) => $data->employmentIds)
            ->toArray();

        DB::table('dimona_period_employment')
            ->whereNotIn('employment_id', $employmentIds)
            ->whereIn('dimona_period_id', function ($query) {
                $query
                    ->select('id')
                    ->from('dimona_periods')
                    ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
                    ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
                    ->whereBetween('start_date', [$this->period->start->format('Y-m-d'), $this->period->end->format('Y-m-d')]);
            })
            ->delete();
    }

    /**
     * Sync a single expected period with actual periods.
     */
    private function syncPeriod(DimonaPeriodData $data): void
    {
        // Strategy 1: If an exact match exists (already linked), nothing to do
        $linkedExactlyMatchingPeriod = $this->findLinkedPeriodWithExactMatch($data);
        if ($linkedExactlyMatchingPeriod) {
            switch ($linkedExactlyMatchingPeriod->state) {
                case DimonaPeriodState::Pending:
                case DimonaPeriodState::Waiting:
                    throw new LogicException('Pending and waiting states should be resolved before syncing.');
                case DimonaPeriodState::New:
                case DimonaPeriodState::Outdated:
                    // will soon be processed, no further action needed
                    return;
                case DimonaPeriodState::Accepted:
                case DimonaPeriodState::Refused:
                    // final state, no further action needed
                    return;
                case DimonaPeriodState::AcceptedWithWarning:
                case DimonaPeriodState::Cancelled:
                    // should be replaced, continue
                    break;
                case DimonaPeriodState::Failed:
                    // TODO: What now? Should we retry?
                    return;
            }
        }

        // Strategy 2: Update an already linked accepted period
        $linkedLooselyMatchingPeriod = $this->findLinkedPeriodWithLooseMatch($data);
        if ($linkedLooselyMatchingPeriod) {
            $this->updatePeriodFields($linkedLooselyMatchingPeriod, $data);

            return;
        }

        // Strategy 3: Reuse an unused period
        $unlinkedLooselyMatchingPeriod = $this->findUnlinkedPeriodWithLooseMatch($data);
        if ($unlinkedLooselyMatchingPeriod) {
            $this->updatePeriodFields($unlinkedLooselyMatchingPeriod, $data);

            return;
        }

        // Strategy 4: Create a new period
        $this->createNewPeriod($data);
    }

    /**
     * Find a period that is already linked to exactly the same employment IDs
     * and matches all fields exactly.
     */
    private function findLinkedPeriodWithExactMatch(DimonaPeriodData $data): ?DimonaPeriod
    {
        return DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->where('worker_type', $data->workerType)
            ->where('joint_commission_number', $data->jointCommissionNumber)
            ->where('start_date', $data->startDate)
            ->where('start_hour', $data->startHour)
            ->where('end_date', $data->endDate)
            ->where('end_hour', $data->endHour)
            ->where('number_of_hours', $data->numberOfHours)
            ->has('dimona_period_employments', '=', count($data->employmentIds))
            ->whereHas('dimona_period_employments', function ($query) use ($data) {
                $query->whereIn('employment_id', $data->employmentIds);
            })
            ->first();
    }

    /**
     * Find a period already linked to any of the employment IDs matching on start_date,
     * worker_type, and joint_commission_number.
     */
    private function findLinkedPeriodWithLooseMatch(DimonaPeriodData $data): ?DimonaPeriod
    {
        return DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->where('worker_type', $data->workerType)
            ->where('joint_commission_number', $data->jointCommissionNumber)
            ->where('start_date', $data->startDate)
            ->whereIn('state', [
                DimonaPeriodState::New,
                DimonaPeriodState::Outdated,
                DimonaPeriodState::Accepted,
            ])
            ->whereHas('dimona_period_employments', function ($query) use ($data) {
                $query->whereIn('employment_id', $data->employmentIds);
            })
            ->first();
    }

    /**
     * Find an unlinked period with matching details that can be reused
     * (matching on start_date, worker_type, and joint_commission_number).
     */
    private function findUnlinkedPeriodWithLooseMatch(DimonaPeriodData $data): ?DimonaPeriod
    {
        return DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->where('worker_type', $data->workerType)
            ->where('joint_commission_number', $data->jointCommissionNumber)
            ->where('start_date', $data->startDate)
            ->whereIn('state', [
                DimonaPeriodState::New,
                DimonaPeriodState::Outdated,
                DimonaPeriodState::Accepted,
            ])
            ->whereDoesntHave('dimona_period_employments')
            ->first();
    }

    private function updatePeriodFields(DimonaPeriod $dimonaPeriod, DimonaPeriodData $data): void
    {
        $dimonaPeriod->start_date = $data->startDate;
        $dimonaPeriod->start_hour = $data->startHour;
        $dimonaPeriod->end_date = $data->endDate;
        $dimonaPeriod->end_hour = $data->endHour;
        $dimonaPeriod->number_of_hours = $data->numberOfHours;

        $wasUpdated = false;
        if ($dimonaPeriod->isDirty()) {
            $dimonaPeriod->state = DimonaPeriodState::Outdated;
            $dimonaPeriod->save();
            $wasUpdated = true;
        }

        $employmentsChanged = $this->linkEmployments($dimonaPeriod, $data->employmentIds);

        if ($wasUpdated || $employmentsChanged) {
            DimonaPeriodUpdated::dispatch($dimonaPeriod);
        }
    }

    private function createNewPeriod(DimonaPeriodData $data): void
    {
        $newPeriod = DimonaPeriod::query()->create([
            'employer_enterprise_number' => $this->employerEnterpriseNumber,
            'worker_social_security_number' => $this->workerSocialSecurityNumber,
            'worker_type' => $data->workerType,
            'joint_commission_number' => $data->jointCommissionNumber,
            'start_date' => $data->startDate,
            'start_hour' => $data->startHour,
            'end_date' => $data->endDate,
            'end_hour' => $data->endHour,
            'number_of_hours' => $data->numberOfHours,
            'location_name' => $data->location->name,
            'location_street' => $data->location->street,
            'location_house_number' => $data->location->houseNumber,
            'location_box_number' => $data->location->boxNumber,
            'location_postal_code' => $data->location->postalCode,
            'location_place' => $data->location->place,
            'location_country' => $data->location->country->value,
            'state' => DimonaPeriodState::New,
        ]);

        $this->linkEmployments($newPeriod, $data->employmentIds);

        DimonaPeriodCreated::dispatch($newPeriod);
    }

    /**
     * Link employment IDs to a dimona period.
     *
     * @param  array<string>  $employmentIds
     * @return bool Whether any employments were added
     */
    private function linkEmployments(DimonaPeriod $period, array $employmentIds): bool
    {
        $employmentsChanged = false;

        foreach ($employmentIds as $employmentId) {
            $inserted = DB::table('dimona_period_employment')->insertOrIgnore([
                'dimona_period_id' => $period->id,
                'employment_id' => $employmentId,
            ]);

            if ($inserted) {
                $employmentsChanged = true;
            }
        }

        return $employmentsChanged;
    }
}
