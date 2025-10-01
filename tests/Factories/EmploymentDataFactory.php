<?php

namespace Hyperlab\Dimona\Tests\Factories;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\Country;
use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Support\Str;

use function fake;

class EmploymentDataFactory
{
    private ?string $id = null;

    private ?string $employerEnterpriseNumber = null;

    private ?int $jointCommissionNumber = null;

    private ?WorkerType $workerType = null;

    private ?string $workerSocialSecurityNumber = null;

    private ?CarbonImmutable $startsAt = null;

    private ?CarbonImmutable $endsAt = null;

    private ?EmploymentLocationData $location = null;

    public static function new(): self
    {
        return new self;
    }

    public function id(string $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    public function employerEnterpriseNumber(string $employerEnterpriseNumber): self
    {
        $clone = clone $this;
        $clone->employerEnterpriseNumber = $employerEnterpriseNumber;

        return $clone;
    }

    public function jointCommissionNumber(int $jointCommissionNumber): self
    {
        $clone = clone $this;
        $clone->jointCommissionNumber = $jointCommissionNumber;

        return $clone;
    }

    public function workerType(WorkerType $workerType): self
    {
        $clone = clone $this;
        $clone->workerType = $workerType;

        return $clone;
    }

    public function workerSocialSecurityNumber(string $workerSocialSecurityNumber): self
    {
        $clone = clone $this;
        $clone->workerSocialSecurityNumber = $workerSocialSecurityNumber;

        return $clone;
    }

    public function startsAt(CarbonImmutable $startsAt): self
    {
        $clone = clone $this;
        $clone->startsAt = $startsAt;

        return $clone;
    }

    public function endsAt(CarbonImmutable $endsAt): self
    {
        $clone = clone $this;
        $clone->endsAt = $endsAt;

        return $clone;
    }

    public function location(EmploymentLocationData $location): self
    {
        $clone = clone $this;
        $clone->location = $location;

        return $clone;
    }

    public function create(): EmploymentData
    {
        return new EmploymentData(
            id: $this->id ?? Str::ulid(),
            employerEnterpriseNumber: $this->employerEnterpriseNumber ?? fake()->numerify('##########'),
            jointCommissionNumber: $this->jointCommissionNumber ?? fake()->randomElement([202, 204]),
            workerType: $this->workerType ?? fake()->randomElement(WorkerType::cases()),
            workerSocialSecurityNumber: $this->workerSocialSecurityNumber ?? fake()->numerify('###########'),
            startsAt: $this->startsAt ?? CarbonImmutable::parse('2025-10-01 07:00'),
            endsAt: $this->endsAt ?? CarbonImmutable::parse('2025-10-01 12:00'),
            location: $this->location ?? $this->defaultLocation(),
        );
    }

    private function defaultLocation(): EmploymentLocationData
    {
        return new EmploymentLocationData(
            name: 'Hyperlab',
            street: 'Op de Koopman',
            houseNumber: '2',
            boxNumber: null,
            postalCode: '3960',
            place: 'Bree',
            country: Country::Belgium,
        );
    }
}
