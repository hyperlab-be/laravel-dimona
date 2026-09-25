<?php

namespace Hyperlab\Dimona\Tests\Factories;

use Carbon\CarbonImmutable;
use Hyperlab\Dimona\Data\EmploymentData;
use Hyperlab\Dimona\Data\EmploymentLocationData;
use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Enums\WorkerType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function fake;
use function is_string;

class EmploymentDataFactory
{
    private ?string $id = null;

    private ?int $jointCommissionNumber = null;

    private ?WorkerType $workerType = null;

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

    public function startsAt(string|CarbonImmutable $startsAt): self
    {
        if (is_string($startsAt)) {
            $startsAt = CarbonImmutable::parse($startsAt, 'Europe/Brussels');
        }

        $clone = clone $this;
        $clone->startsAt = $startsAt;

        return $clone;
    }

    public function endsAt(string|CarbonImmutable $endsAt): self
    {
        if (is_string($endsAt)) {
            $endsAt = CarbonImmutable::parse($endsAt, 'Europe/Brussels');
        }

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

    /**
     * An occasional employment in joint commission 302, from 18:00 to 23:00 on the given day.
     */
    public function occasionalOn(string $day): self
    {
        return $this
            ->id("emp-{$day}")
            ->jointCommissionNumber(302)
            ->workerType(WorkerType::Occasional)
            ->startsAt("{$day} 18:00")
            ->endsAt("{$day} 23:00");
    }

    /**
     * Occasional employments on each of the given days, see occasionalOn().
     *
     * @return Collection<EmploymentData>
     */
    public static function occasionalOnDays(array $days): Collection
    {
        return collect($days)->map(fn (string $day) => self::new()->occasionalOn($day)->create());
    }

    public function create(): EmploymentData
    {
        return new EmploymentData(
            id: $this->id ?? Str::ulid(),
            jointCommissionNumber: $this->jointCommissionNumber ?? fake()->randomElement([202, 204]),
            workerType: $this->workerType ?? fake()->randomElement(collect(WorkerType::cases())->reject(WorkerType::Occasional)->all()),
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
            country: EmploymentLocationCountry::Belgium,
        );
    }
}
