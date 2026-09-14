<?php

declare(strict_types=1);

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use RemoteModels\Exceptions\UnsupportedQuery;
use RemoteModels\Tests\Fixtures\Remote\Github\Commit;
use RemoteModels\Tests\Fixtures\Remote\Github\Event;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('walks link header pages with a cursor', function (): void {
    Http::fake([
        'api.github.com/user/repos' => Http::response(
            [['full_name' => 'a/one'], ['full_name' => 'a/two']],
            200,
            ['Link' => '<https://api.github.com/user/repos?page=2>; rel="next"'],
        ),
        'api.github.com/user/repos?page=2' => Http::response(
            [['full_name' => 'a/three']],
            200,
            ['Link' => '<https://api.github.com/user/repos?page=3>; rel="next"'],
        ),
        'api.github.com/user/repos?page=3' => Http::response([['full_name' => 'a/four']]),
    ]);

    expect(Repo::query()->cursor()->pluck('full_name')->all())
        ->toBe(['a/one', 'a/two', 'a/three', 'a/four']);

    Http::assertSentCount(3);
});

it('stops the cursor at the first page the caller needs', function (): void {
    Http::fake([
        'api.github.com/user/repos' => Http::response(
            [['full_name' => 'a/one'], ['full_name' => 'a/two']],
            200,
            ['Link' => '<https://api.github.com/user/repos?page=2>; rel="next"'],
        ),
    ]);

    expect(Repo::query()->cursor()->take(1)->pluck('full_name')->all())->toBe(['a/one']);

    Http::assertSentCount(1);
});

it('walks numbered pages until a short page', function (): void {
    Http::fake(['api.github.com/commits*' => Http::sequence()
        ->push([['sha' => 'aaa'], ['sha' => 'bbb']])
        ->push([['sha' => 'ccc']])]);

    expect(Commit::query()->limit(2)->cursor()->pluck('sha')->all())->toBe(['aaa', 'bbb', 'ccc']);

    Http::assertSentCount(2);
});

it('follows a cursor token from the payload', function (): void {
    Http::fake(['api.github.com/events*' => Http::sequence()
        ->push(['data' => [['id' => 1]], 'meta' => ['next' => 'abc']])
        ->push(['data' => [['id' => 2]], 'meta' => ['next' => null]])]);

    expect(Event::query()->cursor()->pluck('id')->all())->toBe([1, 2]);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'after=abc'));
});

it('paginates with the total the api reports', function (): void {
    Http::fake(['api.github.com/commits*' => Http::response([
        'total' => 42,
        'data' => [['sha' => 'aaa'], ['sha' => 'bbb']],
    ])]);

    $page = Commit::query()->paginate(2, page: 3);

    expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->total())->toBe(42)
        ->and($page->lastPage())->toBe(21)
        ->and($page->items())->toHaveCount(2);
});

it('refuses to paginate when the api reports no total', function (): void {
    Http::fake(['api.github.com/commits*' => Http::response([['sha' => 'aaa']])]);

    Commit::query()->paginate(2);
})->throws(UnsupportedQuery::class, 'cannot read a total');

it('stops the cursor when the next page repeats the current one', function (): void {
    Http::fake(['api.github.com/user/repos*' => Http::response(
        [['full_name' => 'a/one']],
        200,
        ['Link' => '<https://api.github.com/user/repos>; rel="next"'],
    )]);

    expect(Repo::query()->cursor()->pluck('full_name')->all())->toBe(['a/one', 'a/one']);

    Http::assertSentCount(2);
});

it('simple paginates from the link header', function (): void {
    Http::fake(['api.github.com/user/repos*' => Http::response(
        [['full_name' => 'a/one']],
        200,
        ['Link' => '<https://api.github.com/user/repos?page=2>; rel="next"'],
    )]);

    $page = Repo::query()->simplePaginate(1);

    expect($page)->toBeInstanceOf(Paginator::class)
        ->and($page->hasMorePages())->toBeTrue();
});

it('simple paginates to the last page when no link header comes back', function (): void {
    Http::fake(['api.github.com/user/repos*' => Http::response([['full_name' => 'a/one']])]);

    expect(Repo::query()->simplePaginate(1)->hasMorePages())->toBeFalse();
});
