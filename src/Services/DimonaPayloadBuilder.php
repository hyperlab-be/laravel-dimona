<?php

namespace Hyperlab\Dimona\Services;

use Hyperlab\Dimona\Data\DimonaData;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Str;

class DimonaPayloadBuilder
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function buildCreatePayload(DimonaData $data): array
    {
        $payload = [
            'employer' => [
                'enterpriseNumber' => $data->employerEnterpriseNumber,
            ],
            'worker' => [
                'ssin' => Str::remove(['.', '-', ' '], $data->workerSocialSecurityNumber),
            ],
            'dimonaIn' => [
                'features' => [
                    'jointCommissionNumber' => match ($data->jointCommissionNumber) {
                        302, 304 => 'XXX',
                        default => $data->jointCommissionNumber,
                    },
                    'workerType' => match ($data->workerType) {
                        WorkerType::Student => 'STU',
                        WorkerType::Flexi => 'FLX',
                        WorkerType::Other => 'OTH',
                    },
                ],
            ],
        ];

        if ($data->workerType === WorkerType::Student) {
            $payload['dimonaIn']['plannedHoursNumber'] = ceil($data->startsAt->diffInHours($data->endsAt, true));
            $payload['dimonaIn']['studentPlaceOfWork'] = [
                'name' => $data->location->name,
                'address' => [
                    'street' => $data->location->street,
                    'houseNumber' => $data->location->houseNumber,
                    'boxNumber' => $data->location->boxNumber,
                    'postCode' => $data->location->postalCode,
                    'municipality' => [
                        'code' => NisCodeService::new()->getNisCodeForMunicipality($data->location->postalCode),
                        'name' => $data->location->place,
                    ],
                    'country' => NisCodeService::new()->getNisCodeForCountry($data->location->country),
                ],
            ];
        }

        $startsAt = $data->startsAt->setTimezone('Europe/Brussels');
        $endsAt = $data->endsAt->setTimezone('Europe/Brussels');

        if ($data->workerType === WorkerType::Flexi) {
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

    public function buildUpdatePayload(DimonaPeriod $dimonaPeriod, DimonaData $data): array
    {
        $payload = [
            'dimonaUpdate' => [
                'periodId' => intval($dimonaPeriod->reference),
            ],
        ];

        if ($data->workerType === WorkerType::Student) {
            $payload['dimonaUpdate']['plannedHoursNumber'] = ceil($data->startsAt->diffInHours($data->endsAt, true));
        }

        $startsAt = $data->startsAt->setTimezone('Europe/Brussels');
        $endsAt = $data->endsAt->setTimezone('Europe/Brussels');

        if ($data->workerType === WorkerType::Flexi) {
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

    public function buildCancelPayload(DimonaPeriod $dimonaPeriod, DimonaData $data): array
    {
        return [
            'dimonaCancel' => [
                'periodId' => intval($dimonaPeriod->reference),
            ],
        ];
    }
}
