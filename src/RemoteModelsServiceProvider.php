<?php

declare(strict_types=1);

namespace RemoteModels;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RemoteModels\Console\Commands\MakeRemoteModelCommand;
use RemoteModels\Factories\FakeResponses;

class RemoteModelsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/remote.php', 'remote');

        $this->app->singleton(FakeResponses::class);

        $this->app->singleton(
            RemoteManager::class,
            static fn (Application $app): RemoteManager => new RemoteManager($app->make(Repository::class)),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/remote.php' => config_path('remote.php'),
        ], ['remote-models', 'remote-models-config']);

        $this->publishes([
            __DIR__.'/../stubs' => base_path('stubs'),
        ], ['remote-models', 'remote-models-stubs']);

        $this->commands([
            MakeRemoteModelCommand::class,
        ]);
    }
}
