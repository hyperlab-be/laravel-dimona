<?php

use Hyperlab\Dimona\Facades\Dimona;
use Hyperlab\Dimona\Jobs\DeclareDimona;
use Hyperlab\Dimona\Tests\Models\Employment;
use Illuminate\Support\Facades\Queue;

it('can declare dimona for an employment', function () {
    Queue::fake();

    $employment = Employment::query()->create();

    Dimona::declare($employment);

    Queue::assertPushed(DeclareDimona::class, function (DeclareDimona $job) use ($employment) {
        return $job->dimonaDeclarable === $employment && $job->clientId === null;
    });
});

it('can declare dimona with a specific client', function () {
    Queue::fake();

    $employment = Employment::query()->create();
    $clientId = 'test-client';

    Dimona::client($clientId)->declare($employment);

    Queue::assertPushed(DeclareDimona::class, function (DeclareDimona $job) use ($employment, $clientId) {
        return $job->dimonaDeclarable === $employment && $job->clientId === $clientId;
    });
});
