<?php

declare(strict_types=1);

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RemoteModels\Exceptions\UnsupportedQuery;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;
use RemoteModels\Tests\Fixtures\User;

beforeEach(function (): void {
    Schema::create('users', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('github_login')->nullable();
        $table->string('github_token')->nullable();
    });
});

it('answers the relation of the parent it was created for', function (): void {
    $user = User::create(['name' => 'Taylor', 'github_login' => 'taylorotwell', 'github_token' => 'taylor-token']);

    Repo::factory()->count(3)->for($user)->create();

    Http::preventStrayRequests();

    expect($user->repos)->toHaveCount(3);
});

it('answers the show route with the record it created', function (): void {
    $repo = Repo::factory()->create();

    Http::preventStrayRequests();

    expect(Repo::find($repo->getKey())->name)->toBe($repo->name);
});

it('points the created record back at the local parent', function (): void {
    $user = User::create(['name' => 'Taylor', 'github_login' => 'taylorotwell']);

    $repo = Repo::factory()->for($user)->create();

    expect($repo->owner->login)->toBe('taylorotwell')
        ->and($repo->user->name)->toBe('Taylor');
});

it('marks a created model as existing without sending a request', function (): void {
    Http::preventStrayRequests();

    $repo = Repo::factory()->create();

    expect($repo->exists)->toBeTrue()
        ->and($repo->wasRecentlyCreated)->toBeTrue()
        ->and($repo->topics)->toBe(['php']);

    Http::assertNothingSent();
});

it('sends nothing when the factory only makes models', function (): void {
    Http::preventStrayRequests();

    expect(Repo::factory()->count(2)->make())->toHaveCount(2);

    Http::assertNothingSent();
});

it('adds records to the list on each call', function (): void {
    Repo::factory()->count(2)->create();
    Repo::factory()->create();

    Http::preventStrayRequests();

    expect(Repo::all())->toHaveCount(3);
});

it('refuses to create related records from a remote factory', function (): void {
    Repo::factory()->has(Repo::factory(), 'issues')->create();
})->throws(UnsupportedQuery::class, 'cannot create related records');

it('still refuses a route the factory never created', function (): void {
    Repo::factory()->create();

    Http::preventStrayRequests();

    Repo::find('laravel/framework');
})->throws(StrayRequestException::class);
