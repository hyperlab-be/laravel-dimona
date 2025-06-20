# Interact with Dimona in Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hyperlab/laravel-dimona.svg?style=flat-square)](https://packagist.org/packages/hyperlab/laravel-dimona)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/hyperlab/laravel-dimona/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/hyperlab/laravel-dimona/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/hyperlab/laravel-dimona/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/hyperlab/laravel-dimona/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/hyperlab/laravel-dimona.svg?style=flat-square)](https://packagist.org/packages/hyperlab/laravel-dimona)

This package provides an easy way to interact with the Dimona API in Laravel applications.

## Installation

You can install the package via composer:

```bash
composer require hyperlab/laravel-dimona
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-dimona-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-dimona-config"
```

This is the contents of the published config file:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | The endpoints for the Dimona API.
    |
    */

    'endpoint' => env('DIMONA_ENDPOINT', 'https://services.socialsecurity.be/REST/dimona/v2'),

    'oauth_endpoint' => env('DIMONA_OAUTH_ENDPOINT', 'https://services.socialsecurity.be/REST/oauth/v5/token'),

    /*
    |--------------------------------------------------------------------------
    | Default Client
    |--------------------------------------------------------------------------
    |
    | The default client to use when no client is specified.
    |
    */

    'default_client' => env('DIMONA_DEFAULT_CLIENT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | API Clients
    |--------------------------------------------------------------------------
    |
    | Configure multiple API clients with different credentials.
    | Each client has its own client_id, private_key_path, and enterprise_number.
    |
    */

    'clients' => [

        'default' => [
            'client_id' => env('DIMONA_CLIENT_ID'),
            'private_key_path' => env('DIMONA_PRIVATE_KEY_PATH'),
        ],

        // Add more clients as needed:
        // 'client2' => [
        //     'client_id' => env('DIMONA_CLIENT2_ID'),
        //     'private_key_path' => env('DIMONA_CLIENT2_PRIVATE_KEY_PATH'),
        // ],

    ],

];
```

## Usage

### Configuration

First, configure your Dimona API credentials in the `.env` file:

```
DIMONA_CLIENT_ID=your-client-id
DIMONA_PRIVATE_KEY_PATH=/path/to/your/private-key.pem
```

For multiple clients, you can configure them in the `config/dimona.php` file:

```php
'clients' => [
    'default' => [
        'client_id' => env('DIMONA_CLIENT_ID'),
        'private_key_path' => env('DIMONA_PRIVATE_KEY_PATH'),
    ],
    'client2' => [
        'client_id' => env('DIMONA_CLIENT2_ID'),
        'private_key_path' => env('DIMONA_CLIENT2_PRIVATE_KEY_PATH'),
    ],
],
```

### Basic Usage

Implement the `DimonaDeclarable` interface and use the `HasDimonaPeriods` trait in your model:

```php
use Hyperlab\Dimona\DimonaDeclarable;
use Hyperlab\Dimona\HasDimonaPeriods;
use Hyperlab\Dimona\Data\DimonaData;
use Hyperlab\Dimona\Data\DimonaLocationData;

class Employment extends Model implements DimonaDeclarable
{
    use HasDimonaPeriods;

    public function shouldDeclareDimona(): bool
    {
        // implement logic to determine if Dimona should be declared
    }

    public function getDimonaData(): DimonaData
    {
        return new DimonaData(
            // implement logic to return the Dimona data
        );
    }
}
```

Then, pass a model instance to the `Dimona` facade to declare a Dimona:

```php
// Declare a Dimona
Dimona::declare($employment);

// Use a specific client
Dimona::client('default')->declare($employment);
```

## Events

This package dispatches the following events:

### DimonaPeriodCreated

This event is dispatched when a new DimonaPeriod is created.

```php
use Hyperlab\Dimona\Events\DimonaPeriodCreated;

// Listen for the event
Event::listen(function (DimonaPeriodCreated $event) {
    $dimonaPeriod = $event->dimonaPeriod;
    // Your code here
});
```

### DimonaPeriodStateUpdated

This event is dispatched when a DimonaPeriod's state is updated.

```php
use Hyperlab\Dimona\Events\DimonaPeriodStateUpdated;

// Listen for the event
Event::listen(function (DimonaPeriodStateUpdated $event) {
    $dimonaPeriod = $event->dimonaPeriod;
    // Your code here
});
```

## Testing

```bash
composer test
```

### Mocking the Dimona API

For testing code that interacts with the Dimona API, you can use the `MockDimonaApiClient` class:

```php
use Hyperlab\Dimona\Tests\Mocks\MockDimonaApiClient;

// Create a mock client
$mock = new MockDimonaApiClient();

// Mock responses
$mock
    ->mockCreateDeclaration('test-reference')
    ->mockGetDeclaration('test-reference', 'A')
    ->register();

// Now any code that uses DimonaApiClient will use your mock
```

See the [mock documentation](tests/Mocks/README.md) for more details.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Hyperlab](https://github.com/hyperlab-be)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
