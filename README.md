<div align="center">
    <h1>Remote Models for Laravel</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/eznix86/laravel-remote-models"><img src="https://img.shields.io/packagist/v/eznix86/laravel-remote-models.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/eznix86/laravel-remote-models"><img src="https://img.shields.io/packagist/php-v/eznix86/laravel-remote-models.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://badge.laravel.cloud/badge/eznix86/laravel-remote-models"><img src="https://badge.laravel.cloud/badge/eznix86/laravel-remote-models?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/eznix86/laravel-remote-models/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/eznix86/laravel-remote-models/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/eznix86/laravel-remote-models"><img src="https://img.shields.io/packagist/dt/eznix86/laravel-remote-models.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Use your APIs Eloquently.

A `RemoteModel` is an Eloquent model that reads and writes an HTTP API. Casts,
scopes, accessors, events, serialization, relations and route binding all work
the way you already know them. Only the storage changes.

## Installation

```bash
composer require eznix86/laravel-remote-models
php artisan vendor:publish --tag="remote-models-config"
```

## Connections

Each api is a connection in `config/remote.php`.

```php
return [

    'default' => null,

    'connections' => [
        'stripe' => [
            'url' => env('STRIPE_URL', 'https://api.stripe.com'),
            'token' => env('STRIPE_SECRET'),
            'timeout' => 30,
            'retry' => ['times' => 3, 'sleep' => 100],
        ],
    ],

];
```

A model finds its connection in this order:

1. The `$connection` property, or `#[Connection('stripe')]`.
2. The namespace segment after `Remote`, kebab cased. `App\Models\Remote\Stripe\Customer` reads `stripe`.
3. `remote.default`.

## A model

```bash
php artisan make:remote-model Stripe/Customer --factory
```

This writes `app/Models/Remote/Stripe/Customer.php`, writes
`database/factories/Remote/Stripe/CustomerFactory.php`, and adds the `stripe`
connection to `config/remote.php` when it is missing.

```php
namespace App\Models\Remote\Stripe;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use RemoteModels\Attributes\BelongsToLocal;
use RemoteModels\Attributes\BracketFilters;
use RemoteModels\Attributes\CacheFor;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\Paging;
use RemoteModels\Attributes\Persist;
use RemoteModels\Attributes\RemoteHasMany;
use RemoteModels\Concerns\HasRemoteFactory;
use RemoteModels\RemoteModel;

#[Endpoint('/v1/customers', key: 'id', keyType: 'string')]
#[Persist('/v1/customers/{key}', method: 'post')]
#[BracketFilters]
#[Paging(perPage: 'limit', page: null)]
#[CacheFor(300)]
#[Fillable(['email', 'name', 'description', 'metadata'])]
#[RemoteHasMany(Invoice::class, as: 'invoices', foreignKey: 'customer')]
#[BelongsToLocal(User::class, remoteKey: 'metadata.user_id', ownerKey: 'id', as: 'user')]
class Customer extends RemoteModel
{
    use HasRemoteFactory;

    protected function casts(): array
    {
        return [
            'created' => 'immutable_datetime',
            'livemode' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
```

Every line there answers something Stripe actually does. `#[Persist(method: 'post')]`
because Stripe updates with POST and has no PATCH. `#[BracketFilters]` because
Stripe reads `created[gte]=`. `#[Paging(perPage: 'limit', page: null)]` because
Stripe pages with `limit` and has no page number. `'created' => 'immutable_datetime'`
because Stripe sends unix timestamps, which Eloquent already understands.

The same model without attributes:

```php
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class Customer extends RemoteModel
{
    protected $connection = 'stripe';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected $fillable = ['email', 'name', 'description', 'metadata'];

    protected ?int $cacheFor = 300;

    protected function index(PendingRequest $http, array $query): Response
    {
        return $http->get('/v1/customers', $query);
    }

    protected function persist(PendingRequest $http, array $dirty): Response
    {
        return $http->post("/v1/customers/{$this->getKey()}", $dirty);
    }

    public function invoices(): RemoteHasMany
    {
        return $this->remoteHasMany(Invoice::class, 'customer');
    }

    public function user(): BelongsToLocal
    {
        return $this->belongsToLocal(User::class, 'metadata.user_id', 'id');
    }
}
```

A method always wins over the matching attribute.

## Routes

Five routes cover the model. Each one has a rest default built from `#[Endpoint]`,
or from the plural kebab name of the class when there is no `#[Endpoint]`.

| Route | Default | Attribute | Method |
|---|---|---|---|
| List | `GET /v1/customers` | `#[Index]` | `index(PendingRequest $http, array $query)` |
| Read | `GET /v1/customers/{key}` | `#[Show]` | `show(PendingRequest $http, string\|int $id)` |
| Create | `POST /v1/customers` | `#[Store]` | `store(PendingRequest $http, array $attributes)` |
| Update | `PATCH /v1/customers/{key}` | `#[Persist]` | `persist(PendingRequest $http, array $dirty)` |
| Delete | `DELETE /v1/customers/{key}` | `#[Remove]` | `remove(PendingRequest $http)` |

A uri can hold placeholders. `{key}` and `{id}` read the model key. Any other
name reads an attribute, so `/v1/accounts/{account}/customers` works.

## Action apis

Not every api is resource shaped. When the routes are actions and the key travels
in the body, name each route and set its method.

```php
use Illuminate\Http\Client\Response;

#[Endpoint('/api/users', key: 'id', keyType: 'int')]
#[Index('/api/users/list', method: 'post')]
#[Show('/api/users/get', method: 'post')]
#[Store('/api/users/create')]
#[Persist('/api/users/update', method: 'post')]
#[Remove('/api/users/delete', method: 'post')]
class Person extends RemoteModel
{
    protected $fillable = ['name', 'email'];

    public function resetPassword(): Response
    {
        return $this->newRemoteRequest()->post('/api/users/reset-password', [
            $this->getKeyName() => $this->getKey(),
        ]);
    }
}
```

A uri with no `{key}` placeholder cannot carry the key, so the key goes into the
body instead, under the model key name. `Person::find(7)` posts `{"id": 7}` to
`/api/users/get`, `$person->save()` posts the key with the dirty attributes to
`/api/users/update`, and `$person->delete()` posts `{"id": 7}` to
`/api/users/delete`. A uri that does hold `{key}` keeps the rest behaviour and
sends no extra field.

Anything that is not one of the five routes is a plain method. `newRemoteRequest()`
is public and already carries the connection, the token and the headers.

```php
$person->resetPassword();
```

## Reading and writing

```php
Customer::all();
Customer::find('cus_NffrFeUfNV2Hib');
Customer::on('stripe-connect')->get();
Customer::on(['url' => 'https://api.stripe.com', 'token' => $account->stripe_secret])->get();

$customer = Customer::create(['email' => 'taylor@laravel.com', 'name' => 'Taylor']);
$customer->update(['name' => 'Taylor Otwell']);
$customer->delete();
```

`on()` takes a connection name, an inline config array, or an Eloquent model. A
model answers with `toRemoteConnection()` when it has that method, and with its
`url`, `token` and `headers` attributes when it does not.

Writing goes through a model instance. `Customer::query()->update([...])` throws.

## Queries

You query a remote model with the Eloquent builder you already use. Each chain is
one request, and it is sent when you ask for the result, not before.

```php
$invoices = Invoice::query()
    ->where('customer', $customer->getKey())
    ->where('status', 'open')
    ->where('created', '>=', $startOfYear)
    ->limit(100)
    ->get();
```

```http
GET /v1/invoices?customer=cus_NffrFeUfNV2Hib&status=open&created[gte]=1767225600&limit=100
```

What comes back is an Eloquent collection of models, so the rest is what you would
do with any other collection, and the casts on the model have already run:

```php
$invoices->sum('amount_due');
$invoices->groupBy('currency');
$invoices->first()->created->format('Y-m-d');
```

Scopes compose the same way:

```php
#[Scope]
protected function open(RemoteBuilder $query): RemoteBuilder
{
    return $query->where('status', 'open');
}
```

```php
Invoice::open()->where('customer', 'cus_1')->get();

// GET /v1/invoices?status=open&customer=cus_1
```

`when()` builds a filter only when you have one to apply:

```php
Invoice::query()
    ->when($customer !== null, fn (RemoteBuilder $query) => $query->where('customer', $customer))
    ->when($status !== null, fn (RemoteBuilder $query) => $query->where('status', $status))
    ->get();

// GET /v1/invoices?customer=cus_1
```

Single records and counts read as usual. `find()` and `findOrFail()` go to the
read route, the others read the list route and work on what comes back:

```php
Customer::find('cus_NffrFeUfNV2Hib');   // GET /v1/customers/cus_NffrFeUfNV2Hib
Customer::findOrFail('cus_missing');    // ModelNotFoundException on a 404
Invoice::open()->first();
Invoice::open()->exists();
Invoice::open()->count();
```

`cursor()` walks everything a page at a time and stops as soon as you stop:

```php
Invoice::open()->limit(100)->cursor()->each(function (Invoice $invoice): void {
    // ...
});
```

Everything else is Eloquent too: casts, accessors, `#[Scope]`, model events,
`toArray()`, `toJson()`, and route model binding through `resolveRouteBinding()`.

### What the builder sends

| Builder | Sent |
|---|---|
| `where('status', 'open')` | `status=open` |
| `whereIn('status', [Status::Open, Status::Draft])` | `status=open,draft` |
| `orderBy('created', 'desc')`, `latest()`, `oldest()` | `sort=created&direction=desc` |
| `limit(100)`, `offset(200)`, `forPage(3, 100)` | `per_page=100&page=3` |
| `when()`, `with()`, `count()`, `first()`, `find()` | no parameters of their own |

Values are formatted for the wire: a backed enum sends its value, a `DateTime`
sends ISO 8601, a boolean sends `true` or `false`. A value that cannot go in a
query string throws rather than disappearing. Stripe wants unix seconds on
`created`, so pass `$date->getTimestamp()` there.

Nested fields are dotted columns. `where('metadata.user_id', 7)` sends
`metadata.user_id=7`, and `orderBy('metadata.rank')` sorts on it.

### Filter styles

Comparisons, null checks and negated lists have no single spelling across apis,
so a model names the one its api uses. Without an attribute those clauses throw.

| Attribute | `where('created', '>=', $at)` | `whereNull('paid_at')` |
|---|---|---|
| `#[BracketFilters]` | `created[gte]=...` | `paid_at[null]=true` |
| `#[BracketFilters(prefix: '$')]` | `created[$gte]=...` | `paid_at[$null]=true` |
| `#[SuffixFilters]` | `created__gte=...` | `paid_at__null=true` |
| `#[SuffixFilters(separator: '.')]` | `created.gte=...` | `paid_at.null=true` |

Stripe reads the bracket form, so:

```php
Invoice::query()
    ->where('status', 'open')
    ->where('created', '>=', $startOfYear)
    ->limit(100)
    ->get();

// GET /v1/invoices?status=open&created[gte]=1767225600&limit=100
```

The style covers `!=`, `<`, `<=`, `>`, `>=`, `like`, `not like`, `whereNull`,
`whereNotNull`, `whereNotIn` and `whereBetween`. A nested group of `and` clauses
is flattened, so `where(fn ($q) => $q->where(...)->where(...))` works.

### Page size

`#[Paging]` names the parameters. The default is `per_page` and `page`. Pass
`page: null` for an api that has no page number, and an `offset()` then throws
instead of sending something the api ignores.

### Sparse fieldsets

`select()` is ignored unless the model names the parameter its api reads.

```php
#[Fields]                        // fields=id,email
#[Fields('fields[customer]')]    // fields[customer]=id,email
```

Select only what you read, and keep the key in the list. A model without its key
cannot be updated or deleted.

### What cannot work

| Builder | Why |
|---|---|
| `orWhere()` | a query string ands its parameters |
| two `orderBy()` calls | `sort` takes one column |
| `whereHas()`, `has()`, `doesntHave()` | relation existence is a database join |
| `withCount()`, `withSum()` | same |
| `Customer::query()->update()`, `->delete()` | write through a model instance |

Each of these throws `RemoteModels\Exceptions\UnsupportedQuery` with the reason.
Override `toQuery()` on the model when your api can express something the
defaults cannot.

## Relations

| Direction | Method | Attribute |
|---|---|---|
| Remote to remote | `remoteHasOne()`, `remoteHasMany()` | `#[RemoteHasOne]`, `#[RemoteHasMany]` |
| Remote to Eloquent | `belongsToLocal()` | `#[BelongsToLocal]` |
| Eloquent to remote | `hasOneRemote()`, `hasManyRemote()` | `#[HasOneRemote]`, `#[HasManyRemote]` |

A customer holds its invoices, and the same customer points back at the local user
that owns it:

```php
$customer->invoices;    // GET /v1/invoices?customer=cus_NffrFeUfNV2Hib
$customer->user;        // one database query on metadata.user_id
```

Add `RemoteModels\Concerns\InteractsWithRemoteModels` to an Eloquent model to give
it `hasOneRemote()` and `hasManyRemote()`.

```php
use RemoteModels\Concerns\InteractsWithRemoteModels;
use RemoteModels\Relations\HasManyRemote;

class User extends Model
{
    use InteractsWithRemoteModels;

    public function invoices(): HasManyRemote
    {
        return $this->hasManyRemote(Invoice::class, 'customer', 'stripe_id');
    }
}
```

The second argument is the query parameter the api filters on, the third is the
local column that fills it. A relation sends no filter unless you name one,
because a query parameter cannot be guessed from a class name.

`via()` sets the uri of the relation instead. It takes a template string, a
closure that receives the parent model, or an `Illuminate\Support\Uri`. A query
string in the uri becomes default query parameters, and a `where()` on the
relation overrides them.

```php
$this->remoteHasMany(LineItem::class)->via('/v1/invoices/{key}/lines');
$this->remoteHasMany(Invoice::class)->via(Uri::of('/v1/invoices')->withQuery(['status' => 'open']));
$this->remoteHasMany(Invoice::class)->via(fn (Customer $customer): string => "/v1/customers/{$customer->getKey()}/invoices");
```

`on()` sets the connection, and takes a closure that receives the parent model,
which is how one model serves many tenants:

```php
return $this->hasManyRemote(Invoice::class, 'customer', 'stripe_id')
    ->on(fn (self $user): array => [
        'url' => 'https://api.stripe.com',
        'token' => $user->team->stripe_secret,
    ]);
```

Eager loading sends one request per parent, all of them concurrent.
`Customer::with('user')` stays one request and one database query.

```php
Customer::with('user')->get();
User::with('invoices')->get();
$customer->invoices()->where('status', 'open')->get();
```

A `via` uri that is eager loaded must be a template such as
`/v1/invoices/{key}/lines`, or a closure. A uri the relation method already
interpolated only holds for the parent that built it, so eager loading it throws.

## Pages

`cursor()` walks the pages one record at a time and stops as soon as the caller
stops reading.

```php
Invoice::query()->limit(100)->cursor();
Invoice::query()->paginate(30);
Invoice::query()->simplePaginate(30);
```

Pick how the next page is found:

| Attribute | Next page |
|---|---|
| `#[LinkPagination]` (default) | `Link: <...>; rel="next"` |
| `#[PagePagination]` | `?page=` until a short page comes back |
| `#[CursorPagination]` | a token read from the payload |

Stripe fits none of the three, because its next cursor is the id of the last
record. Override `nextPage()` and return where to go:

```php
use RemoteModels\NextPage;

public function nextPage(Response $response, array $query, array $records): ?NextPage
{
    if ($response->json('has_more') !== true || $records === []) {
        return null;
    }

    $last = end($records);

    return new NextPage(query: array_merge($query, [
        'starting_after' => $last['id'],
    ]));
}
```

`paginate()` needs a total. It reads `total`, `total_count` or `meta.total`, and
throws when none of them is there. Override `total()` on the model, or use
`simplePaginate()`.

A next page uri is split with `Illuminate\Support\Uri`, so the filters you set
before paging survive the page turn even when the api leaves them out of its own
next link.

## Cache

`#[CacheFor(300)]` or `protected ?int $cacheFor = 300;` caches read requests for
that many seconds. The key covers the connection, the uri and the query
parameters. A write forgets the cached record of that model.

## Testing

A factory writes into `Http::fake()` instead of a database.

```php
use Illuminate\Support\Facades\Http;

Customer::factory()->count(3)->for($user)->create();

Http::preventStrayRequests();

$user->customers->count(); // 3
```

`create()` answers the list route and the record route of the model. `for($user)`
fills the remote key that `belongsToLocal()` reads. `make()` sends nothing.

## Custom clients

Stripe takes form encoded bodies rather than json. A driver settles that once for
every model on the connection:

```php
use Illuminate\Support\Facades\Http;
use RemoteModels\Facades\Remote;

Remote::extend('stripe', fn (array $config) => Http::baseUrl($config['url'])
    ->withToken($config['token'])
    ->asForm());
```

## Publishing

```bash
php artisan vendor:publish --tag="remote-models"
php artisan vendor:publish --tag="remote-models-config"
php artisan vendor:publish --tag="remote-models-stubs"
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Remote Models for Laravel! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Bruno Bernard](https://github.com/eznix86)
- [All Contributors](../../contributors)

## License

Remote Models for Laravel is open-sourced software licensed under the [MIT license](LICENSE.md).
