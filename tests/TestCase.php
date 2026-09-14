<?php

declare(strict_types=1);

namespace RemoteModels\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use RemoteModels\RemoteModelsServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            RemoteModelsServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set('remote.connections.github', [
            'url' => 'https://api.github.com',
            'token' => 'github-token',
            'headers' => ['X-GitHub-Api-Version' => '2022-11-28'],
        ]);

        $app->make(Repository::class)->set('remote.connections.github-enterprise', [
            'url' => 'https://github.example.com/api/v3',
            'token' => 'enterprise-token',
        ]);

        $app->make(Repository::class)->set('remote.connections.helpdesk', [
            'url' => 'https://helpdesk.test',
        ]);

        $app->make(Repository::class)->set('remote.connections.stripe', [
            'url' => 'https://api.stripe.com',
            'token' => 'sk_test_123',
        ]);

        $app->make(Repository::class)->set('remote.connections.shop', [
            'url' => 'https://shop.test',
        ]);

        $app->make(Repository::class)->set('remote.connections.rpc', [
            'url' => 'https://rpc.test',
            'token' => 'rpc-token',
        ]);
    }
}
