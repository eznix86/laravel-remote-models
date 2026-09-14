<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use RemoteModels\Facades\Remote;
use RemoteModels\RemoteManager;
use RemoteModels\RemoteModelsServiceProvider;

it('merges the package config under the remote key', function (): void {
    expect(config('remote.connections.github.url'))->toBe('https://api.github.com')
        ->and(config()->has('remote.default'))->toBeTrue();
});

it('resolves the manager as a singleton', function (): void {
    expect(app(RemoteManager::class))->toBe(app(RemoteManager::class))
        ->and(Remote::getFacadeRoot())->toBe(app(RemoteManager::class));
});

it('publishes the config file under the config tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(RemoteModelsServiceProvider::class, $tag);

    expect(array_map(realpath(...), array_keys($paths)))->toContain(realpath(__DIR__.'/../../config/remote.php'))
        ->and(array_values($paths))->toContain(config_path('remote.php'));
})->with(['remote-models', 'remote-models-config']);

it('publishes the stubs under the stub tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(RemoteModelsServiceProvider::class, $tag);

    expect(array_map(realpath(...), array_keys($paths)))->toContain(realpath(__DIR__.'/../../stubs'))
        ->and(array_values($paths))->toContain(base_path('stubs'));
})->with(['remote-models', 'remote-models-stubs']);

it('registers the generator command', function (): void {
    expect(array_keys(app(Kernel::class)->all()))->toContain('make:remote-model');
});
