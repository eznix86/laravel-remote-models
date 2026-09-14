---
name: laravel-remote-models-development
description: >
  Configure and apply the Laravel Remote Models package in Laravel applications.
license: MIT
metadata:
  author: Bruno Bernard
---

# Laravel Remote Models

Use this skill when a Laravel application needs to read or write an HTTP API through Eloquent.

## Primary Goal

- apply the `eznix86/laravel-remote-models` package's public API in the smallest correct way

## Workflow

### 1. Install and configure

```bash
composer require eznix86/laravel-remote-models
php artisan vendor:publish --tag="remote-models-config"
```

Add one entry per API to `config/remote.php`. Only `url` is required.

```php
'connections' => [
    'stripe' => [
        'url' => env('STRIPE_URL', 'https://api.stripe.com'),
        'token' => env('STRIPE_SECRET'),
        'timeout' => 30,
        'retry' => ['times' => 3, 'sleep' => 100],
    ],
],
```

### 2. Generate the model

```bash
php artisan make:remote-model Stripe/Customer --factory
```

The command writes `app/Models/Remote/Stripe/Customer.php`, writes
`database/factories/Remote/Stripe/CustomerFactory.php` with `--factory`, and adds
the `stripe` connection to `config/remote.php` when it is missing. The first path
segment names the connection, or pass `--connection=`.

The stub declares the routes as attributes. Pass `--methods` for the route method
style instead.

### 3. Declare the model

`RemoteModel` extends `Illuminate\Database\Eloquent\Model`, so all 24 core model
attributes work: `#[Connection]`, `#[Table]`, `#[Fillable]`, `#[Hidden]`,
`#[Appends]`, `#[Scope]`, `#[ObservedBy]`, `#[UseFactory]`, and the rest.

Package attributes cover what core does not:

| Attribute | Sets |
|---|---|
| `#[Endpoint('/v1/customers', key:, keyType:)]` | base path, primary key |
| `#[Index]`, `#[Show]`, `#[Store]`, `#[Persist]`, `#[Remove]` | one route each |
| `#[BracketFilters]`, `#[SuffixFilters]` | how comparisons and null checks are spelled |
| `#[Paging(perPage:, page:)]` | the page size and page number parameters |
| `#[Fields]` | the sparse fieldset parameter for `select()` |
| `#[Headers([...])]` | headers for every request of the model |
| `#[CacheFor(300, store:)]` | cache window for read requests |
| `#[LinkPagination]`, `#[PagePagination]`, `#[CursorPagination]` | how the next page is found |
| `#[RemoteHasOne]`, `#[RemoteHasMany]`, `#[BelongsToLocal]`, `#[HasOneRemote]`, `#[HasManyRemote]` | relations |

The matching method always wins over the attribute. The route methods are
`index`, `show`, `store`, `persist` and `remove`. Each takes a
`Illuminate\Http\Client\PendingRequest` and returns a
`Illuminate\Http\Client\Response`.

### 4. Action apis

When the api is action shaped rather than resource shaped, name each route and
set its method. A uri with no `{key}` placeholder sends the key in the body under
the model key name instead.

```php
#[Endpoint('/api/users', key: 'id', keyType: 'int')]
#[Index('/api/users/list', method: 'post')]
#[Show('/api/users/get', method: 'post')]
#[Store('/api/users/create')]
#[Persist('/api/users/update', method: 'post')]
#[Remove('/api/users/delete', method: 'post')]
class Person extends RemoteModel {}
```

Anything beyond the five routes is a plain method on the model. Call
`newRemoteRequest()` for a client that already carries the connection, the token
and the headers.

```php
public function resetPassword(): Response
{
    return $this->newRemoteRequest()->post('/api/users/reset-password', [
        $this->getKeyName() => $this->getKey(),
    ]);
}
```

### 5. Read and write

```php
Customer::all();
Customer::find('cus_NffrFeUfNV2Hib');
Customer::on('stripe-connect')->get();
Customer::on(['url' => 'https://api.stripe.com', 'token' => $account->stripe_secret])->get();

$customer = Customer::create(['email' => 'taylor@laravel.com']);
$customer->update(['name' => 'Taylor Otwell']);
$customer->delete();
```

A real model often needs three attributes that match the api rather than rest
convention. Stripe, for example, updates with POST, filters with brackets and
pages with `limit`:

```php
#[Endpoint('/v1/customers', key: 'id', keyType: 'string')]
#[Persist('/v1/customers/{key}', method: 'post')]
#[BracketFilters]
#[Paging(perPage: 'limit', page: null)]
class Customer extends RemoteModel {}
```

A chain is one request, sent when you ask for the result:

```php
Invoice::open()
    ->where('customer', $customer->getKey())
    ->where('created', '>=', $startOfYear)
    ->limit(100)
    ->get();

// GET /v1/invoices?status=open&customer=cus_NffrFeUfNV2Hib&created[gte]=1767225600&limit=100
```

What comes back is an Eloquent collection of models with the casts already run, so
`sum()`, `groupBy()` and the rest work as usual. Scopes, `when()`, `first()`,
`exists()`, `count()`, `find()`, `findOrFail()` and `cursor()->each()` all behave
the way they do on a database model.

Query state becomes query parameters: `where` sends `column=value`, `whereIn`
joins with commas, `orderBy` sends `sort` and `direction`, `limit` and `offset`
send `per_page` and `page`. Enums send their value, dates send ISO 8601, booleans
send `true` or `false`. Nested fields are dotted columns, so
`where('state.flagged', true)` sends `state.flagged=true`.

Comparisons, null checks and negated lists need a filter style on the model,
because apis spell them differently:

| Attribute | `where('total', '>=', 10)` | `whereNull('paid_at')` |
|---|---|---|
| `#[BracketFilters]` | `total[gte]=10` | `paid_at[null]=true` |
| `#[SuffixFilters]` | `total__gte=10` | `paid_at__null=true` |

`#[Paging]` names the page size parameter, `per_page` and `page` by default.

Add `#[Fields]` to send `select()` as a sparse fieldset. Without it `select()` is
ignored.

`orWhere()`, a second `orderBy()`, `whereHas()` and `withCount()` throw
`RemoteModels\Exceptions\UnsupportedQuery`, because a query string ands its
parameters and relation existence is a database join. Override `toQuery()` on the
model when the api can express more.

Write through a model instance. `Customer::query()->update([...])` throws.

### 6. Relate

Add `RemoteModels\Concerns\InteractsWithRemoteModels` to an Eloquent model for
`hasOneRemote()` and `hasManyRemote()`. A `RemoteModel` already has
`remoteHasOne()`, `remoteHasMany()` and `belongsToLocal()`.

```php
public function invoices(): HasManyRemote
{
    return $this->hasManyRemote(Invoice::class, 'customer', 'stripe_id');
}
```

The second argument is the query parameter the api filters on, the third is the
local column that fills it. A relation sends no filter unless you name one. For a
per tenant token, pass a closure to `on()`:

```php
return $this->hasManyRemote(Invoice::class, 'customer', 'stripe_id')
    ->on(fn (self $user): array => [
        'url' => 'https://api.stripe.com',
        'token' => $user->team->stripe_secret,
    ]);
```

Use `via('/v1/invoices/{key}/lines')` for a nested uri. It also takes a closure
that receives the parent, or an `Illuminate\Support\Uri`. A query string in the
uri becomes default query parameters that a `where()` overrides. Eager loading
sends one concurrent request per parent, so the `via` uri has to be a template or
a closure.

### 7. Test

```php
Customer::factory()->count(3)->for($user)->create();

Http::preventStrayRequests();

expect($user->customers)->toHaveCount(3);
```

`create()` registers `Http::fake()` answers for the list route and the record
route of the model. It sends no request. `make()` sends no request either.

## Rules, References, and Templates

Read before executing:

- `config/remote.php` for the connection keys
- `README.md` for the full attribute and relation tables

## Examples

- Read a third party API with one model: add the connection, run `make:remote-model Stripe/Charge`, set `#[Endpoint('/v1/charges')]`, then call `Charge::all()`.
- Give each user their own token: keep the model on the shared connection, and pass the user config through `Customer::on([...])` or `->on(fn ($user) => ...)` on the relation.
- Send form encoded bodies for an api that wants them: `Remote::extend('stripe', fn (array $config) => Http::baseUrl($config['url'])->withToken($config['token'])->asForm())`.
- Cache a slow list for five minutes: add `#[CacheFor(300)]` to the model.

## Anti-patterns

- do not call `update()` or `delete()` on a query, write through a model instance
- do not eager load a `via` uri that the relation method already interpolated, pass a template such as `/v1/invoices/{key}/lines`
- do not add a foreign key to a remote relation unless the API really takes that query parameter
- do not expect `paginate()` to work when the API reports no total, use `simplePaginate()`
