<?php

namespace Hyperlab\Dimona\Services;

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

        if ($dimonaPeriod->worker_type === WorkerType::Flexi) {
            $payload['dimonaIn']['startDate'] = $dimonaPeriod->start_date;
            $payload['dimonaIn']['startHour'] = $dimonaPeriod->start_hour;
            $payload['dimonaIn']['endDate'] = $dimonaPeriod->end_date;
            $payload['dimonaIn']['endHour'] = $dimonaPeriod->end_hour;
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

        if ($dimonaPeriod->worker_type === WorkerType::Flexi) {
            $payload['dimonaUpdate']['startDate'] = $dimonaPeriod->start_date;
            $payload['dimonaUpdate']['startHour'] = $dimonaPeriod->start_hour;
            $payload['dimonaUpdate']['endDate'] = $dimonaPeriod->end_date;
            $payload['dimonaUpdate']['endHour'] = $dimonaPeriod->end_hour;
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
}
