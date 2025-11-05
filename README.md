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

Call the `declare` method on the `Dimona` facade with a period and collection of `EmploymentData` objects.

```php
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriodImmutable;
use Hyperlab\Dimona\Data\EmploymentData;
use Illuminate\Support\Collection;

$period = CarbonPeriodImmutable::dates(
    CarbonImmutable::startOfWeek(),
    CarbonImmutable::endOfWeek(),
);

$employments = new Collection([
    new EmploymentData(...),
    new EmploymentData(...),
    new EmploymentData(...),
]);

// Declare a Dimona
Dimona::declare($period, $employments);

// Use a specific client
Dimona::client('default')->declare($period, $employments);
```

Use the `HasDimonaPeriods` trait in your employment model:

```php
use Hyperlab\Dimona\HasDimonaPeriods;

class Employment extends Model
{
    use HasDimonaPeriods;
}
```

## How It Works

### Overview

The Laravel Dimona package integrates with the **Belgian Dimona API** (Declaration of Immediate Employment), which is required by Belgian law to declare worker employment periods to social security authorities. The package provides a complete, production-ready solution with automatic retries, smart syncing, and comprehensive error handling.

### Architecture

The package is built around several key concepts:

#### **Models**

- **DimonaPeriod**: Represents a work period that needs to be declared to Dimona
  - Tracks employer, worker, dates, hours, location, and worker type
  - States: `New`, `Outdated`, `Pending`, `Accepted`, `AcceptedWithWarning`, `Refused`, `Waiting`, `Cancelled`, `Failed`

- **DimonaDeclaration**: Audit trail of API declarations (In/Update/Cancel)
  - Belongs to a DimonaPeriod
  - Stores payload, reference, state, and anomalies from the API

- **DimonaWorkerTypeException**: Stores worker type exceptions
  - When the API rejects a flexi/student declaration, the package automatically stores an exception and retries with a different worker type

#### **Services**

- **DimonaApiClient**: Handles HTTP communication with the Dimona API
  - OAuth2 authentication using JWT with RSA private key
  - Automatic token caching for efficiency

- **DimonaService**: High-level service for period operations
  - Creates, updates, and cancels dimona periods
  - Syncs declaration states with the API

- **DimonaPayloadBuilder**: Builds API payloads for different declaration types and worker types

### Main Workflow

When you call `Dimona::declare()`, the package dispatches the `SyncDimonaPeriodsJob` which orchestrates a **7-phase workflow**:

#### **Phase 1: Sync Pending Declarations**
- Queries all periods with `Pending` or `Waiting` states
- Calls the Dimona API to check the status of each declaration
- Updates period and declaration states based on API responses
- Handles worker type exceptions when the API rejects specific worker types

#### **Phase 2: Compute Expected Periods**
Transforms raw employment data into expected Dimona periods:
- Groups employments by joint commission, worker type, and start date
- Applies different rules per worker type:
  - **Flexi**: One period per employment with exact start/end times
  - **Student**: Aggregates hours for the day, tracks location changes
  - **Other**: Aggregates employments for the start date
- Applies worker type exceptions (overrides based on past API responses)

#### **Phase 3: Sync Expectations with Actual Periods**
Smart matching algorithm to minimize API calls:
- For each expected period, the package tries to:
  1. Find an exact match (already synced) → no action needed
  2. Update a linked period (marks as `Outdated`) → sends update declaration
  3. Reuse an unlinked period → links it to the employment
  4. Create a new period (state: `New`) → sends in declaration
- Detaches deleted employments from periods

#### **Phase 4: Cancel Unwanted Periods**
- Cancels periods without employments
- Cancels periods with `AcceptedWithWarning` state (if configured)

#### **Phase 5: Update Declarations**
Sends update declarations for periods with `Outdated` state

#### **Phase 6: Create Declarations**
Sends "in" declarations for periods with `New` state

#### **Phase 7: Backoff & Retry**
If any periods are still pending, re-dispatches the job with exponential backoff:
- 1st retry: 1 second
- 2nd retry: 60 seconds
- 3rd retry: 3600 seconds (1 hour)

### Worker Type Handling

The package automatically handles Belgian worker type requirements:

- **Flexi workers**: Must declare exact start/end times for each shift
- **Student workers**: Can aggregate hours per day
- **Other workers**: Standard declarations

When the API rejects a declaration due to worker type issues (e.g., "flexi requirements not met"), the package:
1. Stores a `DimonaWorkerTypeException` for that worker
2. Automatically marks the period as `Outdated`
3. Retries with the correct worker type
4. Caches the exception to avoid future errors

### State Management

The package uses a robust state machine to track the lifecycle of periods:

```
New → Pending → Accepted
                   ↓
                AcceptedWithWarning
                   ↓
                Cancelled

New → Outdated → Pending → Accepted

Pending → Refused
Pending → Failed
```

Each state transition is logged, and events are fired for integration with your application logic.

### Key Features

- **Smart Syncing**: Minimizes API calls by intelligently matching expected periods with existing ones
- **Automatic Retries**: Exponential backoff for pending declarations ensures eventual consistency
- **Multi-Client Support**: Manage multiple API credentials for different employers
- **Audit Trail**: Complete history of all declarations for compliance
- **Worker Type Resolution**: Automatic handling of type mismatches and exceptions
- **Job-Based**: Asynchronous processing with Laravel queue support
- **OAuth2 Authentication**: Secure JWT-based authentication with token caching
- **Event-Driven**: Dispatches events for integration with application logic

### Example Flow

```php
// 1. You declare employments
Dimona::declare($employerEnterpriseNumber, $workerSSN, $period, $employments);

// 2. Job dispatched → Phase 2: Compute expected periods
// Creates DimonaPeriod with state: New

// 3. Phase 6: Create declarations
// Sends "in" declaration to API
// Period state: New → Pending

// 4. Phase 7: Backoff & retry (1 second later)
// Phase 1: Sync pending declarations
// API returns: Accepted
// Period state: Pending → Accepted

// 5. Events fired:
// - DimonaPeriodCreated
// - DimonaPeriodStateUpdated (New → Pending)
// - DimonaPeriodStateUpdated (Pending → Accepted)
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
