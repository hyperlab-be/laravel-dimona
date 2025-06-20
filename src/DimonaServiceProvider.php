<?php

namespace Hyperlab\Dimona;

use Hyperlab\Dimona\Facades\Dimona;
use Hyperlab\Dimona\Services\DimonaApiClientManager;
use Hyperlab\Dimona\Services\DimonaPayloadBuilder;
use Hyperlab\Dimona\Services\NisCodeService;
use Hyperlab\Dimona\Services\WorkerTypeExceptionService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DimonaServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-dimona')
            ->hasConfigFile('dimona')
            ->hasMigration('create_dimona_tables');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(DimonaApiClientManager::class, fn () => new DimonaApiClientManager);
        $this->app->singleton(DimonaPayloadBuilder::class, fn () => new DimonaPayloadBuilder);
        $this->app->singleton(NisCodeService::class, fn () => new NisCodeService);
        $this->app->singleton(WorkerTypeExceptionService::class, fn () => new WorkerTypeExceptionService);
        $this->app->singleton(DimonaManager::class, fn ($app) => new DimonaManager($app->make(DimonaApiClientManager::class)));
        $this->app->alias(DimonaManager::class, Dimona::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            Dimona::class,
            DimonaApiClientManager::class,
            DimonaPayloadBuilder::class,
            DimonaManager::class,
            NisCodeService::class,
            WorkerTypeExceptionService::class,
        ];
    }
}
