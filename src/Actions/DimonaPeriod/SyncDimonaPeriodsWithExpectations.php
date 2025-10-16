<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncDimonaPeriodsWithExpectations
{
    private string $employerEnterpriseNumber;

    private string $workerSocialSecurityNumber;

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
        string $employerEnterpriseNumber, string $workerSocialSecurityNumber, Collection $expectedDimonaPeriods
    ): void {
        $this->employerEnterpriseNumber = $employerEnterpriseNumber;
        $this->workerSocialSecurityNumber = $workerSocialSecurityNumber;

        $expectedDimonaPeriods->each(fn (DimonaPeriodData $data) => $this->syncPeriod($data));
    }

    /**
     * Sync a single expected period with actual periods.
     */
    private function syncPeriod(DimonaPeriodData $data): void
    {
        // Strategy 1: Try to find and update an already linked period
        $linkedPeriod = $this->findLinkedPeriod($data);
        if ($linkedPeriod) {
            $this->updatePeriod($linkedPeriod, $data);

            return;
        }

        // Strategy 2: Try to find and reuse an unused period with matching details
        $unusedPeriod = $this->findUnusedPeriod($data);
        if ($unusedPeriod) {
            $this->updatePeriod($unusedPeriod, $data);

            return;
        }

        // Strategy 3: Create a new period
        $this->createNewPeriod($data);
    }

    /**
     * Find a period that is already linked to any of the employment IDs.
     */
    private function findLinkedPeriod(DimonaPeriodData $data): ?DimonaPeriod
    {
        return DimonaPeriod::query()
            ->whereHas('dimona_period_employments', function ($query) use ($data) {
                $query->whereIn('employment_id', $data->employmentIds);
            })
            ->first();
    }

    /**
     * Find an unused accepted period with matching details that can be reused.
     */
    private function findUnusedPeriod(DimonaPeriodData $data): ?DimonaPeriod
    {
        return DimonaPeriod::query()
            ->where('employer_enterprise_number', $this->employerEnterpriseNumber)
            ->where('worker_social_security_number', $this->workerSocialSecurityNumber)
            ->where('worker_type', $data->workerType)
            ->where('joint_commission_number', $data->jointCommissionNumber)
            ->where('start_date', $data->startDate)
            ->where('state', DimonaPeriodState::Accepted)
            ->whereDoesntHave('dimona_period_employments')
            ->first();
    }

    private function updatePeriod(DimonaPeriod $dimonaPeriod, DimonaPeriodData $data): void
    {
        $dimonaPeriod->start_date = $data->startDate;
        $dimonaPeriod->start_hour = $data->startHour;
        $dimonaPeriod->end_date = $data->endDate;
        $dimonaPeriod->end_hour = $data->endHour;
        $dimonaPeriod->number_of_hours = $data->numberOfHours;

        if ($dimonaPeriod->isDirty()) {
            $dimonaPeriod->state = DimonaPeriodState::Outdated;
            $dimonaPeriod->save();
        }

        $this->linkEmployments($dimonaPeriod, $data->employmentIds);
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
    }

    /**
     * Link employment IDs to a dimona period.
     *
     * @param  array<string>  $employmentIds
     */
    private function linkEmployments(DimonaPeriod $period, array $employmentIds): void
    {
        foreach ($employmentIds as $employmentId) {
            DB::table('dimona_period_employment')->insertOrIgnore([
                'dimona_period_id' => $period->id,
                'employment_id' => $employmentId,
            ]);
        }
    }
}
