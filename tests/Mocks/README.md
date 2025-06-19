# Mock Classes for Testing

This directory contains mock classes that can be used in tests to simplify testing code that depends on external services.

## MockDimonaApiClient

The `MockDimonaApiClient` class is a mock implementation of the `DimonaApiClient` class that can be used in tests to easily mock the results of the `createDeclaration` and `getDeclaration` methods.

### Usage

```php
use Hyperlab\Dimona\Tests\Mocks\MockDimonaApiClient;

// Create a new instance of the mock client
$mock = new MockDimonaApiClient();

// Mock responses for createDeclaration
$mock->mockCreateDeclaration('test-reference-1');
$mock->mockCreateDeclaration('test-reference-2');

// Mock responses for getDeclaration
$mock->mockGetDeclaration('test-reference-1', 'A'); // Accepted
$mock->mockGetDeclaration('test-reference-2', 'W', ['some-warning']); // Accepted with warning

// Mock exceptions
$mock->mockDeclarationNotYetProcessed('test-reference-3');
$mock->mockServiceIsDown('test-reference-4');
$mock->mockRequestException('test-reference-5', ['error' => 'custom error']);

// Register the mock in the service container
$mock->register();

// Now you can use the mock in your tests
$result = $mock->createDeclaration([]); // Returns ['reference' => 'test-reference-1']
$result = $mock->getDeclaration('test-reference-1'); // Returns ['reference' => 'test-reference-1', 'result' => 'A', 'anomalies' => []]
```

### Available Methods

#### `mockCreateDeclaration(string $reference): self`

Mocks a successful response for the `createDeclaration` method. The response will contain the given reference.

#### `mockGetDeclaration(string $reference, string $result, array $anomalies = []): self`

Mocks a successful response for the `getDeclaration` method. The response will contain the given reference, result, and anomalies.

#### `mockDeclarationNotYetProcessed(string $reference): self`

Mocks a `DimonaDeclarationIsNotYetProcessed` exception for the `getDeclaration` method when called with the given reference.

#### `mockServiceIsDown(string $reference): self`

Mocks a `DimonaServiceIsDown` exception for the `getDeclaration` method when called with the given reference.

#### `mockRequestException(string $reference, array $responseData = ['error' => 'request error']): self`

Mocks a generic `RequestException` for the `getDeclaration` method when called with the given reference. The response data will be included in the exception.

#### `register(): self`

Registers the mock in the service container, so it will be used whenever `DimonaApiClient::new()` is called.
