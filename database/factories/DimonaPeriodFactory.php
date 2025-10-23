<?php

namespace Hyperlab\Dimona\Database\Factories;

use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
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
            'start_date' => '2025-10-01',
            'start_hour' => '0700',
            'end_date' => '2025-10-01',
            'end_hour' => '1200',
            'number_of_hours' => 5.0,
            'location_name' => 'Test Location',
            'location_street' => 'Test Street',
            'location_house_number' => '123',
            'location_box_number' => null,
            'location_postal_code' => '1000',
            'location_place' => 'Brussels',
            'location_country' => EmploymentLocationCountry::Belgium,
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
