<?php

use Hyperlab\Dimona\Facades\Dimona;
use Hyperlab\Dimona\Jobs\DeclareDimona;
use Hyperlab\Dimona\Tests\Models\TestEmployment;
use Illuminate\Support\Facades\Queue;

it('can declare dimona for an employment', function () {
    Queue::fake();

    $employment = TestEmployment::query()->create();

    Dimona::declare($employment);

    Queue::assertPushed(DeclareDimona::class, function (DeclareDimona $job) use ($employment) {
        return $job->employment === $employment && $job->clientId === null;
    });
});

it('can declare dimona with a specific client', function () {
    Queue::fake();

    $employment = TestEmployment::query()->create();
    $clientId = 'test-client';

    Dimona::client($clientId)->declare($employment);

    Queue::assertPushed(DeclareDimona::class, function (DeclareDimona $job) use ($employment, $clientId) {
        return $job->employment === $employment && $job->clientId === $clientId;
    });
});
