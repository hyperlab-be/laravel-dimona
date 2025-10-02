<?php

namespace Hyperlab\Dimona\Services;

use Hyperlab\Dimona\Data\DimonaPeriodData;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Str;

class DimonaPayloadBuilder
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function buildCreatePayload(DimonaPeriodData $dimonaPeriodData): array
    {
        $payload = [
            'employer' => [
                'enterpriseNumber' => $dimonaPeriodData->employerEnterpriseNumber,
            ],
            'worker' => [
                'ssin' => Str::remove(['.', '-', ' '], $dimonaPeriodData->workerSocialSecurityNumber),
            ],
            'dimonaIn' => [
                'features' => [
                    'jointCommissionNumber' => match ($dimonaPeriodData->jointCommissionNumber) {
                        302, 304 => 'XXX',
                        default => $dimonaPeriodData->jointCommissionNumber,
                    },
                    'workerType' => match ($dimonaPeriodData->workerType) {
                        WorkerType::Student => 'STU',
                        WorkerType::Flexi => 'FLX',
                        WorkerType::Other => 'OTH',
                    },
                ],
            ],
        ];

        if ($dimonaPeriodData->workerType === WorkerType::Student) {
            $payload['dimonaIn']['plannedHoursNumber'] = ceil($dimonaPeriodData->startsAt->diffInHours($dimonaPeriodData->endsAt, true));
            $payload['dimonaIn']['studentPlaceOfWork'] = [
                'name' => $dimonaPeriodData->location->name,
                'address' => [
                    'street' => $dimonaPeriodData->location->street,
                    'houseNumber' => $dimonaPeriodData->location->houseNumber,
                    'boxNumber' => $dimonaPeriodData->location->boxNumber,
                    'postCode' => $dimonaPeriodData->location->postalCode,
                    'municipality' => [
                        'code' => NisCodeService::new()->getNisCodeForMunicipality($dimonaPeriodData->location->postalCode),
                        'name' => $dimonaPeriodData->location->place,
                    ],
                    'country' => NisCodeService::new()->getNisCodeForCountry($dimonaPeriodData->location->country),
                ],
            ];
        }

        $startsAt = $dimonaPeriodData->startsAt->setTimezone('Europe/Brussels');
        $endsAt = $dimonaPeriodData->endsAt->setTimezone('Europe/Brussels');

        if ($dimonaPeriodData->workerType === WorkerType::Flexi) {
            $payload['dimonaIn']['startDate'] = $startsAt->format('Y-m-d');
            $payload['dimonaIn']['startHour'] = $startsAt->format('Hi');
            $payload['dimonaIn']['endDate'] = $endsAt->format('Y-m-d');
            $payload['dimonaIn']['endHour'] = $endsAt->format('Hi');
        } else {
            $payload['dimonaIn']['startDate'] = $startsAt->format('Y-m-d');
            $payload['dimonaIn']['endDate'] = $startsAt->format('Y-m-d');
        }

        return $payload;
    }

    public function buildUpdatePayload(DimonaPeriod $dimonaPeriod, DimonaPeriodData $dimonaPeriodData): array
    {
        $payload = [
            'dimonaUpdate' => [
                'periodId' => intval($dimonaPeriod->reference),
            ],
        ];

        if ($dimonaPeriodData->workerType === WorkerType::Student) {
            $payload['dimonaUpdate']['plannedHoursNumber'] = ceil($dimonaPeriodData->startsAt->diffInHours($dimonaPeriodData->endsAt, true));
        }

        $startsAt = $dimonaPeriodData->startsAt->setTimezone('Europe/Brussels');
        $endsAt = $dimonaPeriodData->endsAt->setTimezone('Europe/Brussels');

        if ($dimonaPeriodData->workerType === WorkerType::Flexi) {
            $payload['dimonaIn']['startDate'] = $startsAt->format('Y-m-d');
            $payload['dimonaIn']['startHour'] = $startsAt->format('Hi');
            $payload['dimonaIn']['endDate'] = $endsAt->format('Y-m-d');
            $payload['dimonaIn']['endHour'] = $endsAt->format('Hi');
        } else {
            $payload['dimonaIn']['startDate'] = $startsAt->format('Y-m-d');
            $payload['dimonaIn']['endDate'] = $startsAt->format('Y-m-d');
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
