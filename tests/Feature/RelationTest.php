<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RemoteModels\Exceptions\RelationNotSupported;
use RemoteModels\Exceptions\UnsupportedEagerLoad;
use RemoteModels\Tests\Fixtures\Member;
use RemoteModels\Tests\Fixtures\Remote\Github\Issue;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;
use RemoteModels\Tests\Fixtures\Remote\Github\Star;
use RemoteModels\Tests\Fixtures\User;

beforeEach(function (): void {
    Http::preventStrayRequests();

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('github_login')->nullable();
        $table->string('github_token')->nullable();
    });

    Schema::create('members', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
});

it('reads a remote has many through the via uri', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response(['full_name' => 'laravel/framework']),
        'api.github.com/repos/laravel/framework/issues*' => Http::response(['items' => [
            ['id' => 1, 'title' => 'Crash'],
            ['id' => 2, 'title' => 'Typo'],
        ]]),
    ]);

    $repo = Repo::find('laravel/framework');

    expect($repo->issues)->toHaveCount(2)
        ->and($repo->issues->first())->toBeInstanceOf(Issue::class)
        ->and($repo->issues->first()->title)->toBe('Crash');
});

it('narrows a remote has many with a where before the request', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response(['full_name' => 'laravel/framework']),
        'api.github.com/repos/laravel/framework/issues?state=open' => Http::response(['items' => [['id' => 1]]]),
    ]);

    $repo = Repo::find('laravel/framework');

    expect($repo->issues()->where('state', 'open')->get())->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'state=open'));
});

it('reads a local model through belongsToLocal', function (): void {
    User::create(['name' => 'Taylor', 'github_login' => 'taylorotwell']);

    Http::fake(['api.github.com/repos/laravel/framework' => Http::response([
        'full_name' => 'laravel/framework',
        'owner' => ['login' => 'taylorotwell'],
    ])]);

    expect(Repo::find('laravel/framework')->user->name)->toBe('Taylor');
});

it('returns null from belongsToLocal when no local row matches', function (): void {
    Http::fake(['api.github.com/repos/laravel/framework' => Http::response([
        'full_name' => 'laravel/framework',
        'owner' => ['login' => 'nobody'],
    ])]);

    expect(Repo::find('laravel/framework')->user)->toBeNull();
});

it('eager loads belongsToLocal with one database query', function (): void {
    User::create(['name' => 'Taylor', 'github_login' => 'taylorotwell']);
    User::create(['name' => 'Nuno', 'github_login' => 'nunomaduro']);

    Http::fake(['api.github.com/user/repos*' => Http::response([
        ['full_name' => 'laravel/framework', 'owner' => ['login' => 'taylorotwell']],
        ['full_name' => 'nunomaduro/collision', 'owner' => ['login' => 'nunomaduro']],
        ['full_name' => 'php/php-src', 'owner' => ['login' => 'php']],
    ])]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $repos = Repo::with('user')->get();

    expect($repos->pluck('user.name')->all())->toBe(['Taylor', 'Nuno', null])
        ->and(DB::getQueryLog())->toHaveCount(1);

    Http::assertSentCount(1);
});

it('eager loads a remote has many with one request per parent', function (): void {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            ['full_name' => 'laravel/framework'],
            ['full_name' => 'laravel/pint'],
        ]),
        'api.github.com/repos/laravel/framework/issues*' => Http::response(['items' => [['id' => 1, 'title' => 'Crash']]]),
        'api.github.com/repos/laravel/pint/issues*' => Http::response(['items' => []]),
    ]);

    $repos = Repo::with('issues')->get();

    expect($repos->first()->issues)->toHaveCount(1)
        ->and($repos->last()->issues)->toBeEmpty();

    Http::assertSentCount(3);
});

it('eager loads a local has many remote with the connection of each parent', function (): void {
    User::create(['name' => 'Taylor', 'github_token' => 'taylor-token']);
    User::create(['name' => 'Nuno', 'github_token' => 'nuno-token']);

    Http::fake(['api.github.com/user/repos*' => Http::sequence()
        ->push([['full_name' => 'laravel/framework']])
        ->push([['full_name' => 'nunomaduro/collision'], ['full_name' => 'nunomaduro/termwind']])]);

    $users = User::with('repos')->get();

    expect($users->first()->repos)->toHaveCount(1)
        ->and($users->last()->repos)->toHaveCount(2);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer taylor-token'));
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer nuno-token'));
});

it('eager loads a model that declares its own index route', function (): void {
    User::create(['name' => 'Taylor']);
    User::create(['name' => 'Nuno']);

    Http::fake(['helpdesk.test/v2/tickets*' => Http::sequence()
        ->push([['id' => 1, 'subject' => 'Printer']])
        ->push([['id' => 2, 'subject' => 'Laptop'], ['id' => 3, 'subject' => 'Screen']])]);

    $users = User::with('tickets')->get();

    expect($users->first()->tickets->pluck('subject')->all())->toBe(['Printer'])
        ->and($users->last()->tickets)->toHaveCount(2);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'user_id=1'));
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'user_id=2'));
});

it('reads a lazy has many remote with the connection of the parent', function (): void {
    $user = User::create(['name' => 'Taylor', 'github_token' => 'taylor-token']);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([['full_name' => 'laravel/framework']]),
    ]);

    expect($user->repos)->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer taylor-token'));
});

it('builds relations declared by attributes', function (): void {
    User::create(['name' => 'Taylor', 'github_login' => 'taylorotwell']);

    Http::fake([
        'api.github.com/user/starred*' => Http::response([
            ['full_name' => 'laravel/framework', 'owner' => ['login' => 'taylorotwell']],
        ]),
        'api.github.com/repos/laravel/framework/issues*' => Http::response(['items' => [['id' => 1, 'title' => 'Crash']]]),
    ]);

    $star = Star::all()->first();

    expect($star->user->name)->toBe('Taylor')
        ->and($star->issues->first()->title)->toBe('Crash');
});

it('names an attribute relation after the related model when no name is given', function (): void {
    Member::create(['name' => 'Taylor']);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([['full_name' => 'laravel/framework']]),
    ]);

    expect(Member::first()->repos)->toHaveCount(1);
});

it('refuses a belongsToLocal attribute on an eloquent model', function (): void {
    Member::create(['name' => 'Taylor']);

    Member::first()->owner;
})->throws(RelationNotSupported::class, 'is an Eloquent model');

it('reads a via uri that the relation method interpolated', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response(['full_name' => 'laravel/framework']),
        'api.github.com/repos/laravel/framework/issues*' => Http::response(['items' => [['id' => 1]]]),
    ]);

    expect(Repo::find('laravel/framework')->inlineIssues)->toHaveCount(1);
});

it('refuses to eager load a via uri that the relation method interpolated', function (): void {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([['full_name' => 'laravel/framework']]),
    ]);

    Repo::with('inlineIssues')->get();
})->throws(UnsupportedEagerLoad::class, 'Use a template');
