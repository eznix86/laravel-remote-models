<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RemoteModels\Exceptions\UnresolvedUri;
use RemoteModels\Tests\Fixtures\Remote\Github\Label;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('keeps the query of a via uri and the query of the builder', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response(['full_name' => 'laravel/framework']),
        'api.github.com/repos/laravel/framework/issues*' => Http::response(['items' => [['id' => 1]]]),
    ]);

    Repo::find('laravel/framework')->openIssues()->where('labels', 'bug')->get();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'state=open')
        && str_contains($request->url(), 'labels=bug'));
});

it('lets the builder query override a default baked into the via uri', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response(['full_name' => 'laravel/framework']),
        'api.github.com/repos/laravel/framework/issues*' => Http::response(['items' => []]),
    ]);

    Repo::find('laravel/framework')->openIssues()->where('state', 'closed')->get();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'state=closed')
        && ! str_contains($request->url(), 'state=open'));
});

it('takes a fluent Uri as the via of a relation', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response(['full_name' => 'laravel/framework']),
        'api.github.com/issues*' => Http::response(['items' => [['id' => 1]]]),
    ]);

    expect(Repo::find('laravel/framework')->taggedIssues)->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'filter=tagged'));
});

it('refuses a via uri that still holds placeholders', function (): void {
    Repo::query()->via('/repos/{key}/issues');
})->throws(UnresolvedUri::class, 'Expand the placeholders');

it('carries the caller filters onto the next page', function (): void {
    Http::fake([
        'api.github.com/user/repos?page=2' => Http::response([['full_name' => 'a/two']]),
        'api.github.com/user/repos*' => Http::response(
            [['full_name' => 'a/one']],
            200,
            ['Link' => '<https://api.github.com/user/repos?page=2>; rel="next"'],
        ),
    ]);

    Repo::where('visibility', 'private')->cursor()->all();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'page=2')
        && str_contains($request->url(), 'visibility=private'));
});

it('joins a base url and an endpoint that has no leading slash', function (): void {
    $label = Label::factory()->create();

    Http::preventStrayRequests();

    expect(Label::find($label->getKey())->color)->toBe('ff0000');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.github.com/labels/'));
});
