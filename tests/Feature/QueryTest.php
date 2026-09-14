<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use RemoteModels\Exceptions\UnsupportedQuery;
use RemoteModels\Tests\Fixtures\OrderStatus;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;
use RemoteModels\Tests\Fixtures\Remote\Shop\Article;
use RemoteModels\Tests\Fixtures\Remote\Shop\Comment;
use RemoteModels\Tests\Fixtures\Remote\Shop\Invoice;
use RemoteModels\Tests\Fixtures\Remote\Shop\Order;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('counts with a where and a whereNull', function (): void {
    Http::fake(['shop.test/invoices*' => Http::response([['id' => 1], ['id' => 2]])]);

    $count = Invoice::query()
        ->where('customer_id', 7)
        ->whereNull('paid_at')
        ->count();

    expect($count)->toBe(2);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'customer_id=7')
        && str_contains(urldecode($request->url()), 'paid_at__null=true'));
});

it('sends a date comparison, a null check and an ordering', function (): void {
    Date::setTestNow('2026-09-14T10:00:00+00:00');

    Http::fake(['shop.test/articles*' => Http::response([])]);

    Article::query()
        ->where('published_at', '<=', now())
        ->whereNull('archived_at')
        ->latest('published_at')
        ->get();

    $url = lastUrl();

    expect($url)->toContain('published_at__lte=2026-09-14T10:00:00+00:00')
        ->toContain('archived_at__null=true')
        ->toContain('sort=published_at')
        ->toContain('direction=desc');

    Date::setTestNow();
});

it('sends bracket filters for a conditional range', function (): void {
    Http::fake(['shop.test/orders*' => Http::response([])]);

    $merchantId = 12;
    $status = null;

    Order::query()
        ->when($merchantId !== null, fn (Builder $query) => $query->where('merchant_id', $merchantId))
        ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
        ->where('created_at', '>=', '2026-01-01')
        ->where('created_at', '<=', '2026-12-31')
        ->get();

    $url = lastUrl();

    expect($url)->toContain('merchant_id=12')
        ->toContain('created_at[gte]=2026-01-01')
        ->toContain('created_at[lte]=2026-12-31')
        ->not->toContain('status');
});

it('sends a whereIn of backed enums as their values', function (): void {
    Http::fake(['shop.test/orders*' => Http::response([])]);

    Order::query()
        ->whereIn('status', [OrderStatus::Pending, OrderStatus::PaymentFailed])
        ->oldest()
        ->get();

    $url = lastUrl();

    expect($url)->toContain('status=pending,payment_failed')
        ->toContain('direction=asc');
});

it('flattens a nested group of and clauses', function (): void {
    Http::fake(['shop.test/orders*' => Http::response([])]);

    Order::query()
        ->where(fn (Builder $query) => $query->where('number', 'like', '%INV%')->where('paid', true))
        ->get();

    $url = lastUrl();

    expect($url)->toContain('number[like]=%INV%')
        ->toContain('paid=true');
});

it('turns a whereBetween into a pair of bounds', function (): void {
    Http::fake(['shop.test/orders*' => Http::response([])]);

    Order::query()->whereBetween('total', [10, 99])->get();

    $url = lastUrl();

    expect($url)->toContain('total[gte]=10')->toContain('total[lte]=99');
});

it('turns a whereNotIn into a negated list', function (): void {
    Http::fake(['shop.test/orders*' => Http::response([])]);

    Order::query()->whereNotIn('status', ['draft', 'void'])->get();

    expect(lastUrl())->toContain('status[nin]=draft,void');
});

it('reads a relation of a model found by key', function (): void {
    Http::fake([
        'shop.test/articles/9/comments*' => Http::response([['id' => 3, 'body' => 'First']]),
        'shop.test/articles/9' => Http::response(['id' => 9, 'title' => 'Hello']),
    ]);

    $article = Article::query()->findOrFail(9);

    $comments = $article->comments()->latest()->get();

    expect($article->title)->toBe('Hello')
        ->and($comments)->toHaveCount(1)
        ->and($comments->first()->body)->toBe('First');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'articles/9/comments')
        && str_contains($request->url(), 'direction=desc'));
});

it('throws when the key is missing', function (): void {
    Http::fake(['shop.test/articles/404' => Http::response([], 404)]);

    Article::query()->findOrFail(404);
})->throws(ModelNotFoundException::class);

it('refuses an operator when the model declares no filter style', function (): void {
    Repo::query()->where('stars', '>=', 10)->get();
})->throws(UnsupportedQuery::class, 'has no filter style');

it('refuses a whereNull when the model declares no filter style', function (): void {
    Repo::query()->whereNull('archived_at')->get();
})->throws(UnsupportedQuery::class, 'has no filter style');

it('refuses an orWhere inside a nested group', function (): void {
    Order::query()
        ->where(fn (Builder $query) => $query->where('a', 1)->orWhere('b', 2))
        ->get();
})->throws(UnsupportedQuery::class, 'cannot translate an orWhere clause');

it('refuses a value it cannot put in a query string', function (): void {
    Order::query()->where('customer', new stdClass)->get();
})->throws(UnsupportedQuery::class, 'cannot send a value of type [stdClass]');

it('refuses whereHas because relation existence is a database join', function (): void {
    Repo::query()->whereHas('issues', fn (Builder $query) => $query->where('state', 'open'))->get();
})->throws(UnsupportedQuery::class, 'Relation existence is a database join');

it('refuses withCount', function (): void {
    Repo::query()->withCount('issues')->get();
})->throws(UnsupportedQuery::class, 'cannot answer withCount');

it('filters on a nested field with a dotted column', function (): void {
    Http::fake(['shop.test/comments*' => Http::response([
        ['id' => 3, 'body' => 'First', 'state' => ['flagged' => true, 'reason' => 'spam']],
    ])]);

    $comment = Comment::query()->where('state.flagged', true)->first();

    expect(lastUrl())->toContain('state.flagged=true')
        ->and($comment->state['flagged'])->toBeTrue()
        ->and(data_get($comment, 'state.reason'))->toBe('spam');
});

it('orders on a nested field', function (): void {
    Http::fake(['shop.test/comments*' => Http::response([])]);

    Comment::query()->orderBy('state.flagged', 'desc')->get();

    expect(lastUrl())->toContain('sort=state.flagged');
});

it('sends a select as a sparse fieldset', function (): void {
    Http::fake(['shop.test/comments*' => Http::response([])]);

    Comment::query()->select('id', 'body', 'state.flagged')->get();

    expect(lastUrl())->toContain('fields=id,body,state.flagged');
});

it('ignores a select when the model names no fields parameter', function (): void {
    Http::fake(['shop.test/orders*' => Http::response([])]);

    Order::query()->select('id', 'total')->get();

    expect(lastUrl())->not->toContain('fields');
});

it('checks a nested field for null', function (): void {
    Http::fake(['shop.test/comments*' => Http::response([])]);

    Comment::query()->whereNull('state.reason')->get();

    expect(lastUrl())->toContain('state.reason__null=true');
});
