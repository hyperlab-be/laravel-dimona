<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Data\DimonaPeriodOperationData;
use Hyperlab\Dimona\Enums\DimonaPeriodOperation;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ComputeDimonaPeriodOperations
{
    public static function new(): static
    {
        return app(static::class);
    }

    /**
     * @param  Collection<Collection<DimonaPeriodData>>  $expectedDimonaPeriods
     * @param  EloquentCollection<DimonaPeriod>  $actualDimonaPeriods
     * @return Collection<DimonaPeriodOperationData>
     */
    public function execute(Collection $expectedDimonaPeriods, Collection $actualDimonaPeriods): Collection
    {
        $diffs = collect();

        // Eager load employment IDs for all periods if it's an Eloquent collection
        if ($actualDimonaPeriods instanceof EloquentCollection) {
            $actualDimonaPeriods->load('dimona_period_employments');
        } else {
            $actualDimonaPeriods->each(fn (DimonaPeriod $period) => $period->load('dimona_period_employments'));
        }

        // Group actual periods by their key (jointCommissionNumber, workerType, date)
        $actualPeriodsGrouped = $actualDimonaPeriods->groupBy(function (DimonaPeriod $period) {
            return json_encode([
                $period->joint_commission_number,
                $period->worker_type->value,
                $period->starts_at->format('Y-m-d'),
            ]);
        });

        // Process each expected period group
        $expectedDimonaPeriods->each(function (Collection $expectedPeriods) use ($actualPeriodsGrouped, $diffs) {
            /** @var DimonaPeriodData $firstExpected */
            $firstExpected = $expectedPeriods->first();

            $key = json_encode([
                $firstExpected->jointCommissionNumber,
                $firstExpected->workerType->value,
                $firstExpected->startsAt->format('Y-m-d'),
            ]);

            $actualPeriods = $actualPeriodsGrouped->get($key, collect());

            // Match expected with actual periods
            foreach ($expectedPeriods as $expected) {
                $matchingActual = $this->findMatchingActual($expected, $actualPeriods);

                if ($matchingActual) {
                    // Skip periods that are pending (will be synced separately)
                    if (! $this->shouldSync($matchingActual)) {
                        // Check if update is needed
                        if ($this->shouldUpdate($expected, $matchingActual)) {
                            $diffs->push(new DimonaPeriodOperationData(
                                type: DimonaPeriodOperation::Update,
                                expected: $expected,
                                actual: $matchingActual,
                            ));
                        }
                        // Check if link is needed
                        if ($this->shouldLink($expected, $matchingActual)) {
                            $diffs->push(new DimonaPeriodOperationData(
                                type: DimonaPeriodOperation::Link,
                                expected: $expected,
                                actual: $matchingActual,
                            ));
                        }
                        // Check if period should be cancelled (AcceptedWithWarning)
                        if ($this->shouldCancel($matchingActual)) {
                            $diffs->push(new DimonaPeriodOperationData(
                                type: DimonaPeriodOperation::Cancel,
                                expected: null,
                                actual: $matchingActual,
                            ));
                        }
                    }
                    // Remove from actual list to track which ones were processed
                    $actualPeriods = $actualPeriods->reject(fn ($p) => $p->id === $matchingActual->id);
                } else {
                    // No matching actual period, needs to be created
                    $diffs->push(new DimonaPeriodOperationData(
                        type: DimonaPeriodOperation::Create,
                        expected: $expected,
                        actual: null,
                    ));
                }
            }

            // Remaining actual periods in this group need to be cancelled
            foreach ($actualPeriods as $actual) {
                $diffs->push(new DimonaPeriodOperationData(
                    type: DimonaPeriodOperation::Cancel,
                    expected: null,
                    actual: $actual,
                ));
            }
        });

        // Find actual periods that don't have any expected match (need to be cancelled)
        $processedKeys = $expectedDimonaPeriods->map(function (Collection $periods) {
            /** @var DimonaPeriodData $first */
            $first = $periods->first();

            return json_encode([
                $first->jointCommissionNumber,
                $first->workerType->value,
                $first->startsAt->format('Y-m-d'),
            ]);
        });

        foreach ($actualPeriodsGrouped as $key => $periods) {
            if (! $processedKeys->contains($key)) {
                foreach ($periods as $actual) {
                    $diffs->push(new DimonaPeriodOperationData(
                        type: DimonaPeriodOperation::Cancel,
                        expected: null,
                        actual: $actual,
                    ));
                }
            }
        }

        return $diffs;
    }

    /**
     * Find the best matching actual period for the expected period.
     */
    private function findMatchingActual(DimonaPeriodData $expected, Collection $actualPeriods): ?DimonaPeriod
    {
        // First, try to find exact match by employment IDs
        $exactMatch = $actualPeriods->first(function (DimonaPeriod $actual) use ($expected) {
            $actualEmploymentIds = $actual->dimona_period_employments->pluck('employment_id')->toArray();

            return count(array_intersect($actualEmploymentIds, $expected->employmentIds)) > 0;
        });

        if ($exactMatch) {
            return $exactMatch;
        }

        // Otherwise, find by date range overlap
        return $actualPeriods->first(function (DimonaPeriod $actual) use ($expected) {
            return $actual->starts_at->eq($expected->startsAt) && $actual->ends_at->eq($expected->endsAt);
        });
    }

    /**
     * Check if a period needs to be synced (has pending state).
     */
    private function shouldSync(DimonaPeriod $actual): bool
    {
        return $actual->state === DimonaPeriodState::Pending;
    }

    /**
     * Check if an actual period needs to be updated based on the expected data.
     */
    private function shouldUpdate(DimonaPeriodData $expected, DimonaPeriod $actual): bool
    {
        return $actual->starts_at->notEqualTo($expected->startsAt) || $actual->ends_at->notEqualTo($expected->endsAt);
    }

    /**
     * Check if the employment IDs of the actual period need to be updated based on the expected data.
     */
    private function shouldLink(DimonaPeriodData $expected, DimonaPeriod $actual): bool
    {
        $actualIds = collect($actual->dimona_period_employments->pluck('employment_id')->toArray())->sort()->values()->toArray();
        $expectedIds = collect($expected->employmentIds)->sort()->values()->toArray();

        return $actualIds !== $expectedIds;
    }

    /**
     * Check if a period should be automatically cancelled.
     */
    private function shouldCancel(DimonaPeriod $actual): bool
    {
        return $actual->state === DimonaPeriodState::AcceptedWithWarning;
    }
}
