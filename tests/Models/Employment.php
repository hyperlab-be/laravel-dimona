<?php

namespace Hyperlab\Dimona\Tests\Models;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\DimonaData;
use Hyperlab\Dimona\Data\DimonaLocationData;
use Hyperlab\Dimona\DimonaDeclarable;
use Hyperlab\Dimona\Enums\Country;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\HasDimonaPeriods;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Employment extends Model implements DimonaDeclarable
{
    use HasDimonaPeriods, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'cancelled' => 'boolean',
    ];

    public function shouldDeclareDimona(): bool
    {
        return ! $this->cancelled;
    }

    public function getDimonaData(): DimonaData
    {
        return new DimonaData(
            employerEnterpriseNumber: '0123456789',
            jointCommissionNumber: 200,
            workerType: WorkerType::Student,
            workerSocialSecurityNumber: '12345678901',
            startsAt: CarbonImmutable::now(),
            endsAt: CarbonImmutable::now()->addDays(7),
            location: new DimonaLocationData(
                name: 'Test Location',
                street: 'Test Street',
                houseNumber: '1',
                boxNumber: null,
                postalCode: '1000',
                place: 'Brussels',
                country: Country::Belgium,
            ),
        );
    }
}
