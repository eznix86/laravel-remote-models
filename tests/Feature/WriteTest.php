<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RemoteModels\Exceptions\UnsupportedQuery;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('creates through the store route and keeps what the api returned', function (): void {
    Http::fake(['api.github.com/user/repos' => Http::response([
        'full_name' => 'eznix86/demo',
        'name' => 'demo',
        'private' => true,
        'html_url' => 'https://github.com/eznix86/demo',
    ], 201)]);

    $repo = Repo::create(['name' => 'demo', 'private' => true]);

    expect($repo->getKey())->toBe('eznix86/demo')
        ->and($repo->html_url)->toBe('https://github.com/eznix86/demo')
        ->and($repo->exists)->toBeTrue()
        ->and($repo->wasRecentlyCreated)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->data() === ['name' => 'demo', 'private' => true]);
});

it('sends only the dirty attributes through the persist route', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::sequence()
            ->push(['full_name' => 'laravel/framework', 'name' => 'framework', 'description' => 'Old text'])
            ->push(['full_name' => 'laravel/framework', 'name' => 'framework', 'description' => 'New text']),
    ]);

    $repo = Repo::find('laravel/framework');

    $repo->update(['description' => 'New text']);

    expect($repo->description)->toBe('New text');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
        && $request->data() === ['description' => 'New text']);
});

it('skips the request when nothing is dirty', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response(['full_name' => 'laravel/framework', 'name' => 'framework']),
    ]);

    $repo = Repo::find('laravel/framework');

    $repo->save();

    Http::assertSentCount(1);
});

it('deletes through the remove route', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::sequence()
            ->push(['full_name' => 'laravel/framework'])
            ->push([], 204),
    ]);

    $repo = Repo::find('laravel/framework');

    expect($repo->delete())->toBeTrue()
        ->and($repo->exists)->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://api.github.com/repos/laravel/framework');
});

it('fires the model events around a create', function (): void {
    Http::fake(['api.github.com/user/repos' => Http::response(['full_name' => 'eznix86/demo'])]);

    $fired = [];

    Repo::creating(function () use (&$fired): void {
        $fired[] = 'creating';
    });

    Repo::created(function () use (&$fired): void {
        $fired[] = 'created';
    });

    Repo::create(['name' => 'demo']);

    expect($fired)->toBe(['creating', 'created']);
});

it('stops the request when a creating listener returns false', function (): void {
    Repo::creating(fn (): bool => false);

    expect(Repo::create(['name' => 'demo'])->exists)->toBeFalse();

    Http::assertNothingSent();
});

it('throws when the api rejects a write', function (): void {
    Http::fake([
        'api.github.com/user/repos' => Http::response(['message' => 'Bad credentials'], 401),
    ]);

    Repo::create(['name' => 'demo']);
})->throws(RequestException::class);

it('sends an array cast as an array and not as a json string', function (): void {
    Http::fake(['api.github.com/user/repos' => Http::response(['full_name' => 'eznix86/demo'])]);

    $repo = new Repo;
    $repo->name = 'demo';
    $repo->topics = ['php', 'api'];
    $repo->save();

    Http::assertSent(fn (Request $request): bool => $request->data()['topics'] === ['php', 'api']);
});

it('refuses a mass update on a query', function (): void {
    Repo::query()->update(['private' => true]);
})->throws(UnsupportedQuery::class, 'does not support [update] on a query');

it('refuses a mass delete on a query', function (): void {
    Repo::query()->delete();
})->throws(UnsupportedQuery::class, 'does not support [delete] on a query');

afterEach(function (): void {
    Repo::flushEventListeners();
});
