<?php

declare(strict_types=1);

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RemoteModels\Exceptions\UnknownConnection;
use RemoteModels\Facades\Remote;
use RemoteModels\Tests\Fixtures\Account;
use RemoteModels\Tests\Fixtures\Detached;
use RemoteModels\Tests\Fixtures\Helpdesk\Ticket;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;
use RemoteModels\Tests\Fixtures\Remote\GithubEnterprise\Project;
use RemoteModels\Tests\Fixtures\Tenant;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('infers the connection from the namespace segment after Remote', function (): void {
    Http::fake(['api.github.com/user/repos*' => Http::response([])]);

    Repo::all();

    Http::assertSent(
        fn (Request $request): bool => str_starts_with($request->url(), 'https://api.github.com/user/repos'),
    );
});

it('kebab cases a studly namespace segment into a connection name', function (): void {
    Http::fake(['github.example.com/api/v3/projects*' => Http::response([])]);

    Project::all();

    Http::assertSent(
        fn (Request $request): bool => str_starts_with($request->url(), 'https://github.example.com/api/v3/projects'),
    );
});

it('uses the connection property when the model declares one', function (): void {
    Http::fake(['helpdesk.test/v2/tickets*' => Http::response([])]);

    Ticket::all();

    Http::assertSent(
        fn (Request $request): bool => str_starts_with($request->url(), 'https://helpdesk.test/v2/tickets'),
    );
});

it('falls back to the default connection from config', function (): void {
    config()->set('remote.default', 'helpdesk');

    Http::fake(['helpdesk.test/detacheds*' => Http::response([])]);

    Detached::all();

    Http::assertSent(
        fn (Request $request): bool => str_starts_with($request->url(), 'https://helpdesk.test/detacheds'),
    );
});

it('throws when the model has no connection to fall back on', function (): void {
    Detached::all();
})->throws(UnknownConnection::class, 'has no remote connection');

it('throws when the named connection is missing from config', function (): void {
    Repo::on('gitlab')->get();
})->throws(UnknownConnection::class, 'is not defined in config/remote.php');

it('sends the token and the headers from the connection config', function (): void {
    Http::fake(['api.github.com/*' => Http::response([])]);

    Repo::all();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer github-token')
        && $request->hasHeader('X-GitHub-Api-Version', '2022-11-28'));
});

it('sends the headers declared by the model attribute', function (): void {
    Http::fake(['api.github.com/*' => Http::response([])]);

    Repo::all();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Test', 'repo'));
});

it('switches connection for one query with a name', function (): void {
    Http::fake(['github.example.com/*' => Http::response([])]);

    Repo::on('github-enterprise')->get();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://github.example.com/api/v3/user/repos')
        && $request->hasHeader('Authorization', 'Bearer enterprise-token'));
});

it('switches connection for one query with an inline config array', function (): void {
    Http::fake(['inline.test/*' => Http::response([])]);

    Repo::on(['url' => 'https://inline.test', 'token' => 'inline-token'])->get();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://inline.test/user/repos')
        && $request->hasHeader('Authorization', 'Bearer inline-token'));
});

it('switches connection for one query with an eloquent model', function (): void {
    Http::fake(['account.test/*' => Http::response([])]);

    $account = new Account(['url' => 'https://account.test', 'token' => 'account-token']);

    Repo::on($account)->get();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://account.test/user/repos')
        && $request->hasHeader('Authorization', 'Bearer account-token'));
});

it('prefers toRemoteConnection on an eloquent model', function (): void {
    Http::fake(['tenant.test/*' => Http::response([])]);

    Repo::on(new Tenant(['url' => 'https://ignored.test']))->get();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://tenant.test/user/repos')
        && $request->hasHeader('Authorization', 'Bearer tenant-token'));
});

it('keeps the connection on the models it hydrates', function (): void {
    Http::fake(['inline.test/*' => Http::response([['full_name' => 'laravel/framework']])]);

    $repo = Repo::on(['url' => 'https://inline.test'])->get()->first();

    $repo->delete();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://inline.test/repos/laravel/framework'
        && $request->method() === 'DELETE');
});

it('builds the client from a registered driver', function (): void {
    Http::fake(['proxy.test/*' => Http::response([])]);

    Remote::extend('github', fn (array $config): PendingRequest => Http::baseUrl('https://proxy.test')
        ->withHeaders(['X-Proxy' => $config['token']]));

    Repo::all();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://proxy.test/user/repos')
        && $request->hasHeader('X-Proxy', 'github-token'));
});
