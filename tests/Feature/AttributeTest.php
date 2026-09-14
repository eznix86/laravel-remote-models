<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RemoteModels\Exceptions\UnsupportedQuery;
use RemoteModels\Tests\Fixtures\Remote\Github\Fork;
use RemoteModels\Tests\Fixtures\Remote\Github\Gist;
use RemoteModels\Tests\Fixtures\Remote\Github\Owner;
use RemoteModels\Tests\Fixtures\Remote\Github\Release;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('inherits the package attributes of a parent model', function (): void {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([['full_name' => 'laravel/framework']]),
    ]);

    expect(Release::all()->first()->getKey())->toBe('laravel/framework');
});

it('casts a nested record into a remote model without a second request', function (): void {
    Http::fake(['api.github.com/repos/laravel/framework' => Http::response([
        'full_name' => 'laravel/framework',
        'owner' => ['login' => 'laravel', 'id' => 958072],
    ])]);

    $repo = Repo::find('laravel/framework');

    expect($repo->owner)->toBeInstanceOf(Owner::class)
        ->and($repo->owner->login)->toBe('laravel')
        ->and($repo->owner->getKey())->toBe('laravel');

    Http::assertSentCount(1);
});

it('casts booleans and arrays through the casts method', function (): void {
    Http::fake(['api.github.com/repos/laravel/framework' => Http::response([
        'full_name' => 'laravel/framework',
        'private' => 0,
        'topics' => ['php', 'framework'],
    ])]);

    $repo = Repo::find('laravel/framework');

    expect($repo->private)->toBeFalse()
        ->and($repo->topics)->toBe(['php', 'framework']);
});

it('exposes an accessor declared with the eloquent Attribute class', function (): void {
    Http::fake(['api.github.com/repos/laravel/framework' => Http::response([
        'full_name' => 'laravel/framework',
        'clone_url' => 'https://github.com/laravel/framework.git',
    ])]);

    expect(Repo::find('laravel/framework')->clone_command)
        ->toBe('git clone https://github.com/laravel/framework.git');
});

it('refuses a custom builder that does not extend RemoteBuilder', function (): void {
    Fork::all();
})->throws(UnsupportedQuery::class, 'needs a builder that extends RemoteBuilder');

it('lets the core Connection attribute override the namespace', function (): void {
    Http::fake(['github.example.com/api/v3/gists*' => Http::response([])]);

    Gist::all();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer enterprise-token'));
});

it('honours the core Fillable attribute', function (): void {
    Http::fake(['github.example.com/api/v3/gists' => Http::response(['slug' => 'abc'])]);

    Gist::create(['title' => 'Notes', 'starred' => true]);

    Http::assertSent(fn (Request $request): bool => $request->data() === ['title' => 'Notes']);
});

it('honours the core Scope attribute', function (): void {
    Http::fake(['*/gists*' => Http::response([])]);

    Gist::starred()->get();

    Http::assertSent(fn (Request $request): bool => $request['starred'] === 'yes');
});
