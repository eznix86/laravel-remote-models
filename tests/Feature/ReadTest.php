<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RemoteModels\Exceptions\UnexpectedPayload;
use RemoteModels\Exceptions\UnsupportedQuery;
use RemoteModels\Tests\Fixtures\Helpdesk\Ticket;
use RemoteModels\Tests\Fixtures\Remote\Github\Issue;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;
use RemoteModels\Tests\Fixtures\Remote\GithubEnterprise\Project;
use RemoteModels\Tests\Fixtures\Remote\Shop\Order;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('reads the index route declared by an attribute', function (): void {
    Http::fake(['api.github.com/user/repos*' => Http::response([
        ['full_name' => 'laravel/framework', 'name' => 'framework', 'private' => false],
        ['full_name' => 'laravel/pint', 'name' => 'pint', 'private' => true],
    ])]);

    $repos = Repo::all();

    expect($repos)->toHaveCount(2)
        ->and($repos->first()->getKey())->toBe('laravel/framework')
        ->and($repos->last()->private)->toBeTrue();
});

it('reads the index route declared by a method', function (): void {
    Http::fake([
        'helpdesk.test/v2/tickets*' => Http::response([['id' => 7, 'subject' => 'Printer']]),
    ]);

    expect(Ticket::all()->first()->subject)->toBe('Printer');
});

it('turns a where into a query parameter', function (): void {
    Http::fake(['api.github.com/user/repos?visibility=private' => Http::response([])]);

    Repo::where('visibility', 'private')->get();

    Http::assertSent(fn (Request $request): bool => $request['visibility'] === 'private');
});

it('turns a scope into a query parameter', function (): void {
    Http::fake(['api.github.com/user/repos?visibility=public' => Http::response([])]);

    Repo::visibility('public')->get();

    Http::assertSent(fn (Request $request): bool => $request['visibility'] === 'public');
});

it('turns a whereIn into a comma separated query parameter', function (): void {
    Http::fake(['api.github.com/user/repos?state=open%2Cclosed' => Http::response([])]);

    Repo::whereIn('state', ['open', 'closed'])->get();

    Http::assertSent(fn (Request $request): bool => $request['state'] === 'open,closed');
});

it('turns an orderBy into sort and direction', function (): void {
    Http::fake(['api.github.com/user/repos?sort=pushed&direction=desc' => Http::response([])]);

    Repo::query()->orderBy('pushed', 'desc')->get();

    Http::assertSent(fn (Request $request): bool => $request['sort'] === 'pushed' && $request['direction'] === 'desc');
});

it('turns a limit into per_page', function (): void {
    Http::fake(['api.github.com/user/repos?per_page=5' => Http::response([])]);

    Repo::query()->limit(5)->get();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'per_page=5'));
});

it('turns a whole page offset into a page number', function (): void {
    Http::fake(['api.github.com/user/repos?per_page=10&page=3' => Http::response([])]);

    Repo::query()->forPage(3, 10)->get();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'per_page=10')
        && str_contains($request->url(), 'page=3'));
});

it('drops the table prefix that whereKey adds to a column', function (): void {
    Http::fake(['api.github.com/user/repos?full_name=laravel%2Fpint' => Http::response([])]);

    Repo::query()->whereKey('laravel/pint')->get();

    Http::assertSent(fn (Request $request): bool => $request['full_name'] === 'laravel/pint');
});

it('refuses an operator it has no name for', function (): void {
    Order::query()->where('name', 'ilike', 'laravel')->get();
})->throws(UnsupportedQuery::class, 'cannot translate the [ilike] operator');

it('refuses a where clause it has no shape for', function (): void {
    Order::query()->where('flags', '&', 1)->get();
})->throws(UnsupportedQuery::class, 'cannot translate a [Bitwise] where clause');

it('refuses an orWhere', function (): void {
    Repo::where('a', 1)->orWhere('b', 2)->get();
})->throws(UnsupportedQuery::class, 'cannot translate an orWhere clause');

it('refuses a second orderBy', function (): void {
    Repo::query()->orderBy('pushed')->orderBy('name')->get();
})->throws(UnsupportedQuery::class, 'more than one orderBy');

it('refuses an offset that is not a whole number of pages', function (): void {
    Repo::query()->limit(2)->offset(3)->get();
})->throws(UnsupportedQuery::class, 'whole number of pages');

it('reads one record through the show route', function (): void {
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response(['full_name' => 'laravel/framework', 'name' => 'framework']),
    ]);

    expect(Repo::find('laravel/framework')->name)->toBe('framework');
});

it('returns null when the show route answers 404', function (): void {
    Http::fake(['api.github.com/repos/*' => Http::response([], 404)]);

    expect(Repo::find('laravel/missing'))->toBeNull();
});

it('unwraps a data envelope', function (): void {
    Http::fake([
        'github.example.com/api/v3/projects*' => Http::response(['data' => [['id' => 1, 'name' => 'Atlas']]]),
    ]);

    expect(Project::all()->first()->name)->toBe('Atlas');
});

it('reads a list through a records override', function (): void {
    Http::fake([
        'api.github.com/issues*' => Http::response(['items' => [['id' => 3, 'title' => 'Crash']]]),
    ]);

    expect(Issue::all()->first()->title)->toBe('Crash');
});

it('refuses a payload that is not a list of records', function (): void {
    Http::fake(['api.github.com/user/repos*' => Http::response(['message' => 'Bad credentials'])]);

    Repo::all();
})->throws(UnexpectedPayload::class, 'expected the response to hold a list of records');

it('counts by reading the index route', function (): void {
    Http::fake([
        'api.github.com/user/repos*' => Http::response([['full_name' => 'a/b'], ['full_name' => 'c/d']]),
    ]);

    expect(Repo::query()->count())->toBe(2);
});
