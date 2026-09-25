<?php

namespace Hyperlab\Dimona\Services;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Str;

class DimonaPayloadBuilder
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function buildCreatePayload(DimonaPeriod $dimonaPeriod): array
    {
        $payload = [
            'employer' => [
                'enterpriseNumber' => $dimonaPeriod->employer_enterprise_number,
            ],
            'worker' => [
                'ssin' => Str::remove(['.', '-', ' '], $dimonaPeriod->worker_social_security_number),
            ],
            'dimonaIn' => [
                'features' => [
                    'jointCommissionNumber' => match ($dimonaPeriod->joint_commission_number) {
                        302, 304 => 'XXX',
                        default => $dimonaPeriod->joint_commission_number,
                    },
                    'workerType' => match ($dimonaPeriod->worker_type) {
                        WorkerType::Student => 'STU',
                        WorkerType::Flexi => 'FLX',
                        WorkerType::Other => 'OTH',
                        WorkerType::Occasional => 'EXT',
                    },
                ],
            ],
        ];

        if ($dimonaPeriod->worker_type === WorkerType::Student) {
            $payload['dimonaIn']['plannedHoursNumber'] = $dimonaPeriod->number_of_hours ? ceil($dimonaPeriod->number_of_hours) : null;
            $payload['dimonaIn']['studentPlaceOfWork'] = [
                'name' => $dimonaPeriod->location_name,
                'address' => [
                    'street' => $dimonaPeriod->location_street,
                    'houseNumber' => $dimonaPeriod->location_house_number,
                    'boxNumber' => $dimonaPeriod->location_box_number,
                    'postCode' => $dimonaPeriod->location_postal_code,
                    'municipality' => [
                        'code' => NisCodeService::new()->getNisCodeForMunicipality($dimonaPeriod->location_postal_code),
                        'name' => $dimonaPeriod->location_place,
                    ],
                    'country' => NisCodeService::new()->getNisCodeForCountry($dimonaPeriod->location_country),
                ],
            ];
        }

        if ($this->isDeclaredWithHours($dimonaPeriod)) {
            $payload['dimonaIn']['startDate'] = $dimonaPeriod->start_date;
            $payload['dimonaIn']['startHour'] = Str::remove(':', $dimonaPeriod->start_hour);
            $payload['dimonaIn']['endDate'] = $dimonaPeriod->end_date;
            $payload['dimonaIn']['endHour'] = Str::remove(':', $dimonaPeriod->end_hour);
        } elseif ($this->coversConsecutiveDays($dimonaPeriod)) {
            $payload['dimonaIn']['startDate'] = $dimonaPeriod->start_date;
            $payload['dimonaIn']['endDate'] = $dimonaPeriod->end_date;
        } else {
            $payload['dimonaIn']['startDate'] = $dimonaPeriod->start_date;
            $payload['dimonaIn']['endDate'] = $dimonaPeriod->start_date;
        }

        return $payload;
    }

    public function buildUpdatePayload(DimonaPeriod $dimonaPeriod): array
    {
        $payload = [
            'dimonaUpdate' => [
                'periodId' => intval($dimonaPeriod->reference),
            ],
        ];

        if ($dimonaPeriod->worker_type === WorkerType::Student) {
            $payload['dimonaUpdate']['plannedHoursNumber'] = $dimonaPeriod->number_of_hours ? ceil($dimonaPeriod->number_of_hours) : null;
        }

        if ($this->isDeclaredWithHours($dimonaPeriod)) {
            $payload['dimonaUpdate']['startDate'] = $dimonaPeriod->start_date;
            $payload['dimonaUpdate']['startHour'] = Str::remove(':', $dimonaPeriod->start_hour);
            $payload['dimonaUpdate']['endDate'] = $dimonaPeriod->end_date;
            $payload['dimonaUpdate']['endHour'] = Str::remove(':', $dimonaPeriod->end_hour);
        } elseif ($this->coversConsecutiveDays($dimonaPeriod)) {
            $payload['dimonaUpdate']['startDate'] = $dimonaPeriod->start_date;
            $payload['dimonaUpdate']['endDate'] = $dimonaPeriod->end_date;
        } else {
            $payload['dimonaUpdate']['startDate'] = $dimonaPeriod->start_date;
            $payload['dimonaUpdate']['endDate'] = $dimonaPeriod->start_date;
        }

        return $payload;
    }

    public function buildCancelPayload(DimonaPeriod $dimonaPeriod): array
    {
        return [
            'dimonaCancel' => [
                'periodId' => intval($dimonaPeriod->reference),
            ],
        ];
    }

    private function isDeclaredWithHours(DimonaPeriod $dimonaPeriod): bool
    {
        return in_array($dimonaPeriod->worker_type, [WorkerType::Flexi, WorkerType::Occasional], true);
    }

    /**
     * An Other period covering a series of consecutive days is declared up to its last day.
     * Any other period is declared for its start date only: its end date is at most the next day,
     * when the shift runs past midnight.
     */
    private function coversConsecutiveDays(DimonaPeriod $dimonaPeriod): bool
    {
        if ($dimonaPeriod->worker_type !== WorkerType::Other || ! $dimonaPeriod->end_date) {
            return false;
        }

        $startDate = CarbonImmutable::parse($dimonaPeriod->start_date);
        $endDate = CarbonImmutable::parse($dimonaPeriod->end_date);

        return $startDate->diffInDays($endDate) >= 2;
    }
}
