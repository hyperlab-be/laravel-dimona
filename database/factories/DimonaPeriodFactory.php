<?php

namespace Hyperlab\Dimona\Database\Factories;

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\WorkerType;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class DimonaPeriodFactory extends Factory
{
    protected $model = DimonaPeriod::class;

    public function definition(): array
    {
        return [
            'employer_enterprise_number' => '0123456789',
            'worker_social_security_number' => '12345678901',
            'worker_type' => WorkerType::Student,
            'joint_commission_number' => 202,
            'starts_at' => '2025-10-01 07:00:00',
            'ends_at' => '2025-10-01 12:00:00',
            'state' => DimonaPeriodState::Pending,
        ];
    }

    public function withEmployments(array $employmentIds): self
    {
        return $this->afterCreating(function (DimonaPeriod $period) use ($employmentIds) {
            foreach ($employmentIds as $employmentId) {
                $period->dimona_period_employments()->create([
                    'employment_id' => $employmentId,
                ]);
            }
        });
    }
}
