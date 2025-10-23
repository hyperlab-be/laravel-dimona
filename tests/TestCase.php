<?php

namespace Hyperlab\Dimona\Tests;

use Hyperlab\Dimona\DimonaServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Hyperlab\\Dimona\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            DimonaServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Load package migrations
        foreach (File::allFiles(__DIR__.'/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }

        // Load test migrations
        foreach (File::allFiles(__DIR__.'/database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }

        // Configure the client
        config()->set('dimona.clients.test-client', [
            'client_id' => 'test-client-id',
            'private_key_path' => __DIR__.'/test-private-key.pem',
        ]);

        config()->set('dimona.default_client', 'test-client');

        // Configure the employment model for tests
        config()->set('dimona.employment_model', \Hyperlab\Dimona\Tests\Models\Employment::class);
    }
}
