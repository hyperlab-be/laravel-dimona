<?php

namespace Hyperlab\Dimona\Tests\Mocks;

use Hyperlab\Dimona\Exceptions\DimonaDeclarationIsNotYetProcessed;
use Hyperlab\Dimona\Exceptions\DimonaServiceIsDown;
use Hyperlab\Dimona\Services\DimonaApiClient;
use Hyperlab\Dimona\Services\DimonaClientManager;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Mockery;

class MockDimonaApiClient extends DimonaApiClient
{
    protected array $createDeclarationResponses = [];

    protected array $createDeclarationExceptions = [];

    protected array $getDeclarationResponses = [];

    protected array $exceptions = [];

    public function __construct()
    {
        // Skip parent constructor to avoid HTTP client initialization
    }

    /**
     * Create a new instance of the mock client.
     */
    public static function new(?string $clientId = null): static
    {
        return new static;
    }

    /**
     * Mock a successful response for createDeclaration.
     */
    public function mockCreateDeclaration(string $reference): self
    {
        $this->createDeclarationResponses[] = [
            'reference' => $reference,
        ];

        return $this;
    }

    /**
     * Mock a successful response for getDeclaration.
     */
    public function mockGetDeclaration(string $reference, string $result, array $anomalies = []): self
    {
        $this->getDeclarationResponses[$reference] = [
            'reference' => $reference,
            'result' => $result,
            'anomalies' => $anomalies,
        ];

        return $this;
    }

    /**
     * Mock a DimonaDeclarationIsNotYetProcessed exception for getDeclaration.
     */
    public function mockDeclarationNotYetProcessed(string $reference): self
    {
        $this->exceptions[$reference] = new DimonaDeclarationIsNotYetProcessed;

        return $this;
    }

    /**
     * Mock a DimonaServiceIsDown exception for getDeclaration.
     */
    public function mockServiceIsDown(string $reference): self
    {
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('json')->andReturn(['error' => 'service is down']);

        $mockException = Mockery::mock(RequestException::class);
        $mockException->response = $mockResponse;

        $this->exceptions[$reference] = new DimonaServiceIsDown($mockException);

        return $this;
    }

    /**
     * Mock a generic RequestException for getDeclaration.
     */
    public function mockRequestException(string $reference, array $responseData = ['error' => 'request error']): self
    {
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('json')->andReturn($responseData);
        $mockResponse->shouldReceive('status')->andReturn(500);
        $mockResponse->shouldReceive('serverError')->andReturn(true);

        $mockException = Mockery::mock(RequestException::class);
        $mockException->response = $mockResponse;

        $this->exceptions[$reference] = $mockException;

        return $this;
    }

    /**
     * Mock a RequestException for createDeclaration.
     */
    public function mockCreateDeclarationException(array $responseData = ['error' => 'request error']): self
    {
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('json')->andReturn($responseData);
        $mockResponse->shouldReceive('status')->andReturn(500);
        $mockResponse->shouldReceive('serverError')->andReturn(true);

        $mockException = Mockery::mock(RequestException::class);
        $mockException->response = $mockResponse;

        $this->createDeclarationExceptions[] = $mockException;

        return $this;
    }

    /**
     * Override the createDeclaration method to return mocked responses or throw exceptions.
     */
    public function createDeclaration(array $payload): array
    {
        // Check if we should throw an exception
        if (! empty($this->createDeclarationExceptions)) {
            $exception = array_shift($this->createDeclarationExceptions);
            throw $exception;
        }

        // Check if we have a mocked response
        if (empty($this->createDeclarationResponses)) {
            throw new \Exception('No mocked response for createDeclaration. Use mockCreateDeclaration() to set up a response.');
        }

        return array_shift($this->createDeclarationResponses);
    }

    /**
     * Override the getDeclaration method to return mocked responses or throw exceptions.
     */
    public function getDeclaration(string $reference): array
    {
        // Check if we should throw an exception for this reference
        if (isset($this->exceptions[$reference])) {
            $exception = $this->exceptions[$reference];
            unset($this->exceptions[$reference]);
            throw $exception;
        }

        // Check if we have a mocked response for this reference
        if (isset($this->getDeclarationResponses[$reference])) {
            return $this->getDeclarationResponses[$reference];
        }

        throw new \Exception("No mocked response for getDeclaration with reference '{$reference}'. Use mockGetDeclaration() to set up a response.");
    }

    /**
     * Register this mock in the service container.
     */
    public function register(): self
    {
        app()->instance(DimonaClientManager::class, Mockery::mock(DimonaClientManager::class)
            ->shouldReceive('client')
            ->andReturn($this)
            ->getMock());

        return $this;
    }
}
