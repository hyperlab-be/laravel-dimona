<?php

use Hyperlab\Dimona\Exceptions\DimonaDeclarationIsNotYetProcessed;
use Hyperlab\Dimona\Exceptions\DimonaServiceIsDown;
use Hyperlab\Dimona\Exceptions\InvalidDimonaApiRequest;
use Hyperlab\Dimona\Exceptions\InvalidDimonaApiResponse;
use Hyperlab\Dimona\Exceptions\UnableToRetrieveAuthorizationToken;
use Hyperlab\Dimona\Services\DimonaApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Clear the cache before each test
    Cache::flush();

    // Set up test configuration
    Config::set('dimona.endpoint', 'https://api.dimona.test');
    Config::set('dimona.oauth_endpoint', 'https://oauth.dimona.test/token');

    // Create a test client
    $this->clientId = 'test-client-id';
    $this->privateKeyPath = __DIR__.'/../stubs/private-key.pem';

    // Create a stub private key file with a valid format
    $directory = dirname($this->privateKeyPath);
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    // This is a sample RSA private key in PEM format (not a real key, just for testing)
    $privateKey = <<<'EOD'
-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEAuzwQC4ZpGR+nBgZPOw29zGe9ej4b2yI+ti7oLxHf+zMPbO0T
8fQ0quvMeKJlUMwO7UJxv3WuKjl8iSPnwQg5nz2aNmQvFH8qT2fovJsj9FrKNX8P
5r1+LXpUbx7X7xjYHgEkXyKPs6Sg9LN7gE7B8O/ocZ9inIvPqQPRfXjjyODvLBkU
YT+hK5p6DxGQUmv7TCU9O8bPUqLiPnRxDPzEcQRwcxp+vdOqZNQWnIpx5MzNjPyH
pJUxDCiXsNvAQNqk9Y0V6xJz+HzB4wUvZQhd3LwZPGZQogP9xeKkoEqQRcHM0UPR
f1gu8kzwEQlQpvO2lMJuFkR2p2WQxvFvQCU2RQIDAQABAoIBAFtGAm4Kh7sEB5cL
XDqjZ9EuB9vHlHxWRztQsGD2KKvPXVwvXlcAcQAEVCm9+X9lYL8kS1zDNxEZGU+Z
qWAyFrZ0yj/jEY2hnCB0jEdS+yHUbMVJUZPODUK9hzIEz0IMhUxJJ5yxVpP0nqVV
Hb4aTjCwiHF/OSznLeXEYKRSLz2E9JLGe8JmMqQfbRLaKHCSGmQzQ4nYc1sW2Hv7
KQiLvfOYcNBWvnlkDS9lXlfLLuI6+w5lKF5F+JeYdR4JSRB3KA7XB8HbDjQFDRBE
Ql5h6K5S3/mQJU0GcE+qyV/9FxvKGUHLKcjEGLHVvh0RM33XuPFSVhCcYFYYS2sy
TITJhAECgYEA8kIIQBZj0tYKYFwVh6TKnR4LZ4VFhZ4BQlzFf1TdQlcCZ8d+1tWB
0/IKS6ESAzLGIL5V7nFQ6LAMHzQZKNkZZHc7W9Lbf/xP0PbKGjLOvK+YYxnUHlso
nMFXCmQPGru9EygXMHbFNBKpZ+WoVG7k6QnwD5VAKY9cq8J2r8Vqe4ECgYEAxfvr
8fS5zVJpXRomUVQcD4bZl+7LUKXi/tFQs5qzFCj8TiF6MUQQs3AgzFIFVyLMzwHl
Mw9hRrYbvDD1FS0+ATFGIJw2BkmOZBEECmyQUTm/HbKgMJcKJYeF3gUZVvtXKbFn
H9l6Cl5RjTBQYJbwMlm6qGLCvsEAYGPXZy+qZUUCgYEAjWYZxgvQQeIYLJEZUODJ
9290T0uPRQz4xUQeQPiPR1CZuKSBNTk0+GzxP8YvZwgqiUxcNxLADbzJcJXbWsPq
aqvFMQrwi8HLJWH5ikyGFOZcP8VYJHxKJFPmJfK5UhJZCREPrBM5SzUFhv2HZ2kf
Wy1WUsoFsEIe5Rsw0YyZggECgYAQZbGDYnWw0F0oYaVFUZPJHQyZYNrR9xYR7A5m
MxQZC8LJjUBQZ2BKv8iOQHwfIhUDABxyKeVYRE9zDaS2ntWx9BhWZj0CnOvP9Xtw
QxtjfA8+2I+0XGFgvLQNyBKXeHNR6Fek7Vq825Kfb6Vx+7jNE8o5JZ5IYbeBCRGE
1XJXcQKBgHwZolGKm0TKbIrSANbpXJPQKYBGfKGZ1tM7sauYGb0FqcSHIJIBXmYs
3LRi7FV/vRKhoH47GcWBODnHx8uMuuYMMxLr8WKnTKwfUl7ulh6fh9Uz6QQvCvXp
CS1aCGUyNZCQyV7ztMCTSGjgolxGwJQIIJNzsmZFvIvV5ND+cILB
-----END RSA PRIVATE KEY-----
EOD;

    file_put_contents($this->privateKeyPath, $privateKey);
});

afterEach(function () {
    // Clean up the stub private key file
    if (file_exists($this->privateKeyPath)) {
        unlink($this->privateKeyPath);
    }
});

it('can be instantiated with client ID and private key path', function () {
    $client = new DimonaApiClient($this->clientId, $this->privateKeyPath);

    expect($client)->toBeInstanceOf(DimonaApiClient::class)
        ->and($client->clientId)->toBe($this->clientId)
        ->and($client->privateKeyPath)->toBe($this->privateKeyPath);
});

it('can be instantiated using the static new method', function () {
    $client = DimonaApiClient::new();

    expect($client)->toBeInstanceOf(DimonaApiClient::class)
        ->and($client->clientId)->toBe('test-client-id')
        ->and($client->privateKeyPath)->toEndWith('/laravel-dimona/tests/test-private-key.pem');
});

it('can be instantiated with a specific client ID using the static new method', function () {
    config()->set('dimona.clients.specific-client', [
        'client_id' => 'specific-client-id',
        'private_key_path' => 'specific-private-key-path',
    ]);

    $client = DimonaApiClient::new('specific-client');

    expect($client)->toBeInstanceOf(DimonaApiClient::class)
        ->and($client->clientId)->toBe('specific-client-id')
        ->and($client->privateKeyPath)->toBe('specific-private-key-path');
});

it('creates a declaration and returns the reference', function () {
    // Set up the mock responses
    Http::fake([
        'https://oauth.dimona.test/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.dimona.test/declarations' => Http::response([], 200, [
            'Location' => 'https://api.dimona.test/declarations/12345',
        ]),
    ]);

    $client = new DimonaApiClient($this->clientId, $this->privateKeyPath);
    $payload = ['test' => 'payload'];
    $result = $client->createDeclaration($payload);

    expect($result)->toBeArray()
        ->and($result['reference'])->toBe('12345');

    // Verify the request was made with the correct URL and payload
    Http::assertSent(function (Request $request) use ($payload) {
        return $request->url() === 'https://api.dimona.test/declarations' &&
               $request->method() === 'POST' &&
               $request->data() === $payload;
    });
});

it('throws an exception when the declaration reference is missing', function () {
    // Set up the mock responses
    Http::fake([
        'https://oauth.dimona.test/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.dimona.test/declarations' => Http::response([], 200),
    ]);

    $client = new DimonaApiClient($this->clientId, $this->privateKeyPath);
    $client->createDeclaration(['test' => 'payload']);
})->throws(InvalidDimonaApiResponse::class);

it('gets a declaration by reference', function () {
    // Set up the mock responses
    Http::fake([
        'https://oauth.dimona.test/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.dimona.test/declarations/12345' => Http::response([
            'declarationStatus' => [
                'period' => [
                    'id' => '67890',
                ],
                'result' => 'ACCEPTED',
                'anomalies' => [],
            ],
        ]),
    ]);

    $client = new DimonaApiClient($this->clientId, $this->privateKeyPath);
    $result = $client->getDeclaration('12345');

    expect($result)->toBeArray()
        ->and($result['reference'])->toBe('67890')
        ->and($result['result'])->toBe('ACCEPTED')
        ->and($result['anomalies'])->toBeArray();

    // Verify the request was made with the correct URL
    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.dimona.test/declarations/12345' &&
               $request->method() === 'GET';
    });
});

it('throws DimonaDeclarationIsNotYetProcessed when declaration is not found', function () {
    // Set up the mock responses
    Http::fake([
        'https://oauth.dimona.test/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.dimona.test/declarations/12345' => Http::response([], 404),
    ]);

    $client = new DimonaApiClient($this->clientId, $this->privateKeyPath);
    $client->getDeclaration('12345');
})->throws(DimonaDeclarationIsNotYetProcessed::class);

it('throws an exception for invalid requests', function () {
    // Set up the mock responses
    Http::fake([
        'https://oauth.dimona.test/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.dimona.test/declarations/12345' => Http::response([], 400),
    ]);

    $client = new DimonaApiClient($this->clientId, $this->privateKeyPath);
    $client->getDeclaration('12345');
})->throws(InvalidDimonaApiRequest::class);

it('throws DimonaServiceIsDown for server errors', function () {
    // Set up the mock responses
    Http::fake([
        'https://oauth.dimona.test/token' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3600,
        ]),
        'https://api.dimona.test/declarations/12345' => Http::response([], 500),
    ]);

    $client = new DimonaApiClient($this->clientId, $this->privateKeyPath);
    $client->getDeclaration('12345');
})->throws(DimonaServiceIsDown::class);

it('uses cached access token when available and not expired', function () {
    // Set a cached token
    $expirationTime = time() + 3600;
    Cache::put("dimona_access_token_{$this->clientId}", 'cached-access-token');
    Cache::put("dimona_access_token_expiration_date_{$this->clientId}", $expirationTime);

    // Set up the mock response for the declaration request
    Http::fake([
        'https://api.dimona.test/declarations' => Http::response([], 200, [
            'Location' => 'https://api.dimona.test/declarations/12345',
        ]),
    ]);

    // Call a method that would trigger authentication
    $client = new DimonaApiClient($this->clientId, $this->privateKeyPath);
    $client->createDeclaration(['test' => 'payload']);

    // Verify the authentication endpoint was not called
    Http::assertNotSent(function (Request $request) {
        return $request->url() === 'https://oauth.dimona.test/token';
    });
});

it('throws UnableToRetrieveAuthorizationToken when OAuth response does not contain access token', function () {
    // Set up the mock response without an access token
    Http::fake([
        'https://oauth.dimona.test/token' => Http::response([
            'expires_in' => 3600,
            // No access_token in the response
        ]),
    ]);

    // Call a method that would trigger authentication
    $client = new DimonaApiClient($this->clientId, $this->privateKeyPath);
    $client->createDeclaration(['test' => 'payload']);
})->throws(UnableToRetrieveAuthorizationToken::class);
