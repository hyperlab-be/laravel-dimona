<?php

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;

function createDimonaPeriod(): DimonaPeriod
{
    return DimonaPeriod::query()->create([
        'employer_enterprise_number' => '0123456789',
        'worker_social_security_number' => '12345678901',
        'joint_commission_number' => 200,
        'worker_type' => WorkerType::Student,
        'start_date' => '2025-10-01',
        'start_hour' => '08:00',
        'end_date' => '2025-10-01',
        'end_hour' => '17:00',
        'state' => DimonaPeriodState::New,
    ]);
}
