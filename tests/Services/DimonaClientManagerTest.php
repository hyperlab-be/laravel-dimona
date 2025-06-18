<?php

use Hyperlab\Dimona\Services\DimonaApiClient;
use Hyperlab\Dimona\Services\DimonaClientManager;
use Illuminate\Support\Facades\Config;

it('returns the default client when no client ID is provided', function () {
    // Set up the test configuration
    Config::set('dimona.default_client', 'test-client');
    Config::set('dimona.clients.test-client', [
        'client_id' => 'test-client-id',
        'private_key_path' => 'test-private-key-path',
    ]);

    $manager = new DimonaClientManager;
    $client = $manager->client();

    expect($client)->toBeInstanceOf(DimonaApiClient::class)
        ->and($client->clientId)->toBe('test-client-id')
        ->and($client->privateKeyPath)->toBe('test-private-key-path');
});

it('returns a specific client when a client ID is provided', function () {
    // Set up the test configuration
    Config::set('dimona.clients.specific-client', [
        'client_id' => 'specific-client-id',
        'private_key_path' => 'specific-private-key-path',
    ]);

    $manager = new DimonaClientManager;
    $client = $manager->client('specific-client');

    expect($client)->toBeInstanceOf(DimonaApiClient::class)
        ->and($client->clientId)->toBe('specific-client-id')
        ->and($client->privateKeyPath)->toBe('specific-private-key-path');
});

it('caches clients for subsequent requests', function () {
    // Set up the test configuration
    Config::set('dimona.clients.cache-client', [
        'client_id' => 'cache-client-id',
        'private_key_path' => 'cache-private-key-path',
    ]);

    $manager = new DimonaClientManager;
    $client1 = $manager->client('cache-client');
    $client2 = $manager->client('cache-client');

    // Both calls should return the same instance
    expect($client1)->toBe($client2);
});

it('throws an exception when a client is not configured', function () {
    $manager = new DimonaClientManager;
    $manager->client('non-existent-client');
})->throws(Exception::class, 'Dimona client [non-existent-client] is not configured.');
