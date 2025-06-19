<?php

use Hyperlab\Dimona\Exceptions\DimonaDeclarationIsNotYetProcessed;
use Hyperlab\Dimona\Exceptions\DimonaServiceIsDown;
use Hyperlab\Dimona\Services\DimonaApiClient;
use Hyperlab\Dimona\Tests\Mocks\MockDimonaApiClient;
use Illuminate\Http\Client\RequestException;

it('can be instantiated', function () {
    $mock = new MockDimonaApiClient;

    expect($mock)->toBeInstanceOf(MockDimonaApiClient::class)
        ->and($mock)->toBeInstanceOf(DimonaApiClient::class);
});

it('can mock createDeclaration response', function () {
    $mock = new MockDimonaApiClient;
    $mock->mockCreateDeclaration('test-reference');

    $result = $mock->createDeclaration([]);

    expect($result)->toBe(['reference' => 'test-reference']);
});

it('can mock getDeclaration response', function () {
    $mock = new MockDimonaApiClient;
    $mock->mockGetDeclaration('test-reference', 'A', ['some' => 'data']);

    $result = $mock->getDeclaration('test-reference');

    expect($result)->toBe([
        'reference' => 'test-reference',
        'result' => 'A',
        'anomalies' => ['some' => 'data'],
    ]);
});

it('can mock DimonaDeclarationIsNotYetProcessed exception', function () {
    $mock = new MockDimonaApiClient;
    $mock->mockDeclarationNotYetProcessed('test-reference');

    expect(fn () => $mock->getDeclaration('test-reference'))
        ->toThrow(DimonaDeclarationIsNotYetProcessed::class);
});

it('can mock DimonaServiceIsDown exception', function () {
    $mock = new MockDimonaApiClient;
    $mock->mockServiceIsDown('test-reference');

    expect(fn () => $mock->getDeclaration('test-reference'))
        ->toThrow(DimonaServiceIsDown::class);
});

it('can mock RequestException', function () {
    $mock = new MockDimonaApiClient;
    $mock->mockRequestException('test-reference');

    expect(fn () => $mock->getDeclaration('test-reference'))
        ->toThrow(RequestException::class);
});

it('can register itself in the service container', function () {
    $mock = new MockDimonaApiClient;
    $mock->register();

    expect(DimonaApiClient::new())->toBe($mock);
});

it('throws an exception when no createDeclaration response is mocked', function () {
    $mock = new MockDimonaApiClient;

    expect(fn () => $mock->createDeclaration([]))
        ->toThrow(\Exception::class, 'No mocked response for createDeclaration');
});

it('can mock exceptions for createDeclaration', function () {
    $mock = new MockDimonaApiClient;
    $mock->mockCreateDeclarationException(['error' => 'test error']);

    expect(fn () => $mock->createDeclaration([]))
        ->toThrow(RequestException::class);
});

it('throws an exception when no getDeclaration response is mocked', function () {
    $mock = new MockDimonaApiClient;

    expect(fn () => $mock->getDeclaration('test-reference'))
        ->toThrow(\Exception::class, "No mocked response for getDeclaration with reference 'test-reference'");
});

it('can chain mock methods', function () {
    $mock = new MockDimonaApiClient;

    $result = $mock->mockCreateDeclaration('test-reference-1')
        ->mockCreateDeclaration('test-reference-2')
        ->mockGetDeclaration('test-reference-1', 'A')
        ->mockGetDeclaration('test-reference-2', 'W', ['warning']);

    expect($result)->toBe($mock)
        ->and($mock->createDeclaration([]))->toBe(['reference' => 'test-reference-1'])
        ->and($mock->createDeclaration([]))->toBe(['reference' => 'test-reference-2'])
        ->and($mock->getDeclaration('test-reference-1'))->toBe([
            'reference' => 'test-reference-1',
            'result' => 'A',
            'anomalies' => [],
        ])
        ->and($mock->getDeclaration('test-reference-2'))->toBe([
            'reference' => 'test-reference-2',
            'result' => 'W',
            'anomalies' => ['warning'],
        ]);
});
