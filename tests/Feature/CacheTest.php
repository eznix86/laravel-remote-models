<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use RemoteModels\Tests\Fixtures\Helpdesk\Article;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;
use RemoteModels\Tests\Fixtures\Remote\Github\Tag;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('answers a repeated read from the cache', function (): void {
    Http::fake(['api.github.com/tags*' => Http::response([['name' => 'v1'], ['name' => 'v2']])]);

    expect(Tag::all()->pluck('name')->all())->toBe(['v1', 'v2'])
        ->and(Tag::all()->pluck('name')->all())->toBe(['v1', 'v2']);

    Http::assertSentCount(1);
});

it('caches each set of query parameters on its own', function (): void {
    Http::fake(['api.github.com/tags*' => Http::response([['name' => 'v1']])]);

    Tag::where('state', 'draft')->get();
    Tag::where('state', 'live')->get();
    Tag::where('state', 'draft')->get();

    Http::assertSentCount(2);
});

it('reads the cache window from a typed property', function (): void {
    Http::fake(['helpdesk.test/articles*' => Http::response([['id' => 1]])]);

    Article::all();
    Article::all();

    Http::assertSentCount(1);
});

it('keeps the response body, status and headers through the cache', function (): void {
    Http::fake([
        'api.github.com/tags?page=2' => Http::response([['name' => 'v2']]),
        'api.github.com/tags*' => Http::response(
            [['name' => 'v1']],
            200,
            ['Link' => '<https://api.github.com/tags?page=2>; rel="next"'],
        ),
    ]);

    Tag::query()->cursor()->all();

    expect(Tag::query()->cursor()->pluck('name')->all())->toBe(['v1', 'v2']);

    Http::assertSentCount(2);
});

it('forgets the cached record after a write', function (): void {
    Http::fake(['api.github.com/tags/v1' => Http::sequence()
        ->push(['name' => 'v1', 'label' => 'one'])
        ->push(['name' => 'v1', 'label' => 'two'])
        ->push(['name' => 'v1', 'label' => 'two'])]);

    $tag = Tag::find('v1');
    $tag->label = 'two';
    $tag->save();

    expect(Tag::find('v1')->label)->toBe('two');

    Http::assertSentCount(3);
});

it('reads twice when the model sets no cache window', function (): void {
    Http::fake(['api.github.com/user/repos*' => Http::response([['full_name' => 'a/one']])]);

    Repo::all();
    Repo::all();

    Http::assertSentCount(2);
});
