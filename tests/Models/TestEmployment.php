<?php

namespace Hyperlab\Dimona\Tests\Models;

use Hyperlab\Dimona\Data\DimonaData;
use Hyperlab\Dimona\Data\DimonaLocationData;
use Hyperlab\Dimona\Employment;
use Hyperlab\Dimona\Enums\Country;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\HasDimona;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TestEmployment extends Model implements Employment
{
    use HasDimona, HasUlids;

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
            startsAt: Carbon::now(),
            endsAt: Carbon::now()->addDays(7),
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
