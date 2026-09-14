<?php

declare(strict_types=1);

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RemoteModels\Facades\Remote;
use RemoteModels\RemoteBuilder;
use RemoteModels\Tests\Fixtures\Remote\Stripe\Customer;
use RemoteModels\Tests\Fixtures\Remote\Stripe\Invoice;
use RemoteModels\Tests\Fixtures\User;

beforeEach(function (): void {
    Http::preventStrayRequests();

    Schema::create('users', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('stripe_id')->nullable();
    });
});

it('lists customers from the data envelope', function (): void {
    Http::fake([
        'api.stripe.com/v1/customers*' => Http::response([
            'object' => 'list',
            'has_more' => false,
            'data' => [
                ['id' => 'cus_1', 'email' => 'taylor@laravel.com', 'livemode' => false],
                ['id' => 'cus_2', 'email' => 'nuno@laravel.com', 'livemode' => false],
            ],
        ]),
    ]);

    $customers = Customer::all();

    expect($customers)->toHaveCount(2)
        ->and($customers->first()->getKey())->toBe('cus_1')
        ->and($customers->first()->livemode)->toBeFalse();

    Http::assertSent(
        fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk_test_123'),
    );
});

it('filters invoices with the bracket style stripe reads', function (): void {
    Http::fake([
        'api.stripe.com/v1/invoices*' => Http::response(['has_more' => false, 'data' => []]),
    ]);

    Invoice::query()
        ->where('status', 'open')
        ->where('created', '>=', 1767225600)
        ->limit(100)
        ->get();

    $url = urldecode(Http::recorded()->last()[0]->url());

    expect($url)->toContain('status=open')
        ->toContain('created[gte]=1767225600')
        ->toContain('limit=100');
});

it('reads one customer and casts the unix timestamp', function (): void {
    Http::fake([
        'api.stripe.com/v1/customers/cus_1' => Http::response([
            'id' => 'cus_1',
            'email' => 'taylor@laravel.com',
            'created' => 1767225600,
            'metadata' => ['user_id' => '1'],
        ]),
    ]);

    $customer = Customer::find('cus_1');

    expect($customer->email)->toBe('taylor@laravel.com')
        ->and($customer->created->format('Y-m-d'))->toBe('2026-01-01')
        ->and($customer->metadata['user_id'])->toBe('1');
});

it('updates a customer with post because stripe has no patch', function (): void {
    Http::fake([
        'api.stripe.com/v1/customers/cus_1' => Http::sequence()
            ->push(['id' => 'cus_1', 'name' => 'Taylor'])
            ->push(['id' => 'cus_1', 'name' => 'Taylor Otwell']),
    ]);

    $customer = Customer::find('cus_1');

    $customer->update(['name' => 'Taylor Otwell']);

    expect($customer->name)->toBe('Taylor Otwell');

    Http::assertSent(
        fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.stripe.com/v1/customers/cus_1'
            && $request->data() === ['name' => 'Taylor Otwell'],
    );
});

it('sends a form body when the driver asks for one', function (): void {
    Remote::extend('stripe', fn (array $config): PendingRequest => Http::baseUrl($config['url'])
        ->withToken($config['token'])
        ->asForm());

    Http::fake([
        'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_9']),
    ]);

    Customer::create(['email' => 'taylor@laravel.com', 'name' => 'Taylor']);

    Http::assertSent(
        fn (Request $request): bool => $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && $request->body() === 'email=taylor%40laravel.com&name=Taylor',
    );
});

it('reads the invoices of a customer through the foreign key', function (): void {
    Http::fake([
        'api.stripe.com/v1/customers/cus_1' => Http::response(['id' => 'cus_1']),
        'api.stripe.com/v1/invoices*' => Http::response([
            'has_more' => false,
            'data' => [['id' => 'in_1', 'paid' => true]],
        ]),
    ]);

    $invoices = Customer::find('cus_1')->invoices;

    expect($invoices)->toHaveCount(1)
        ->and($invoices->first()->paid)->toBeTrue();

    Http::assertSent(
        fn (Request $request): bool => str_contains($request->url(), 'customer=cus_1'),
    );
});

it('walks stripe pages with the has_more flag and the last id', function (): void {
    Http::fake([
        'api.stripe.com/v1/invoices?limit=2&starting_after=in_2' => Http::response([
            'has_more' => false,
            'data' => [['id' => 'in_3']],
        ]),
        'api.stripe.com/v1/invoices*' => Http::response([
            'has_more' => true,
            'data' => [['id' => 'in_1'], ['id' => 'in_2']],
        ]),
    ]);

    $ids = Invoice::query()->limit(2)->cursor()->pluck('id')->all();

    expect($ids)->toBe(['in_1', 'in_2', 'in_3']);

    Http::assertSent(
        fn (Request $request): bool => str_contains($request->url(), 'starting_after=in_2'),
    );
});

it('matches a customer back to the local user through metadata', function (): void {
    User::query()->create(['name' => 'Taylor', 'stripe_id' => 'cus_1']);

    Http::fake([
        'api.stripe.com/v1/customers/cus_1' => Http::response([
            'id' => 'cus_1',
            'metadata' => ['user_id' => '1'],
        ]),
    ]);

    expect(Customer::find('cus_1')->user->name)->toBe('Taylor');
});

it('answers the customers of the user they were created for', function (): void {
    $user = User::query()->create(['name' => 'Taylor']);

    Customer::factory()->count(3)->for($user)->create();

    Http::preventStrayRequests();

    expect($user->customers)->toHaveCount(3)
        ->and($user->customers->first()->metadata['user_id'])->toBe($user->getKey());
});

it('reads the invoices of a local user', function (): void {
    $user = User::query()->create(['name' => 'Taylor', 'stripe_id' => 'cus_1']);

    Http::fake([
        'api.stripe.com/v1/invoices*' => Http::response([
            'has_more' => false,
            'data' => [['id' => 'in_1'], ['id' => 'in_2']],
        ]),
    ]);

    expect($user->invoices)->toHaveCount(2);

    Http::assertSent(
        fn (Request $request): bool => str_contains($request->url(), 'customer=cus_1'),
    );
});

it('reads a filtered page of invoices in one request', function (): void {
    Http::fake([
        'api.stripe.com/v1/invoices*' => Http::response([
            'has_more' => false,
            'data' => [
                ['id' => 'in_1', 'amount_due' => 1000, 'currency' => 'usd', 'created' => 1767225600, 'paid' => false],
                ['id' => 'in_2', 'amount_due' => 2000, 'currency' => 'usd', 'created' => 1767312000, 'paid' => false],
            ],
        ]),
    ]);

    $invoices = Invoice::query()
        ->where('customer', 'cus_NffrFeUfNV2Hib')
        ->where('status', 'open')
        ->where('created', '>=', 1767225600)
        ->limit(100)
        ->get();

    expect($invoices)->toHaveCount(2)
        ->and($invoices->sum('amount_due'))->toBe(3000)
        ->and($invoices->groupBy('currency')->keys()->all())->toBe(['usd'])
        ->and($invoices->first()->created->format('Y-m-d'))->toBe('2026-01-01');

    Http::assertSentCount(1);

    expect(urldecode(Http::recorded()->last()[0]->url()))
        ->toBe('https://api.stripe.com/v1/invoices?customer=cus_NffrFeUfNV2Hib&status=open&created[gte]=1767225600&limit=100');
});

it('composes a scope with further filters', function (): void {
    Http::fake(['api.stripe.com/v1/invoices*' => Http::response(['has_more' => false, 'data' => []])]);

    Invoice::open()->where('customer', 'cus_1')->get();

    expect(urldecode(Http::recorded()->last()[0]->url()))
        ->toBe('https://api.stripe.com/v1/invoices?status=open&customer=cus_1');
});

it('sends only the filters that are set', function (): void {
    Http::fake(['api.stripe.com/v1/invoices*' => Http::response(['has_more' => false, 'data' => []])]);

    $customer = 'cus_1';
    $status = null;

    Invoice::query()
        ->when($customer !== null, fn (RemoteBuilder $query) => $query->where('customer', $customer))
        ->when($status !== null, fn (RemoteBuilder $query) => $query->where('status', $status))
        ->get();

    expect(urldecode(Http::recorded()->last()[0]->url()))
        ->toBe('https://api.stripe.com/v1/invoices?customer=cus_1');
});

it('answers first, exists and count from the index route', function (): void {
    Http::fake([
        'api.stripe.com/v1/invoices*' => Http::response([
            'has_more' => false,
            'data' => [['id' => 'in_1'], ['id' => 'in_2']],
        ]),
    ]);

    expect(Invoice::open()->count())->toBe(2)
        ->and(Invoice::open()->exists())->toBeTrue()
        ->and(Invoice::open()->first())->toBeInstanceOf(Invoice::class);
});

it('walks every invoice one page at a time', function (): void {
    Http::fake([
        'api.stripe.com/v1/invoices?status=open&limit=2&starting_after=in_2' => Http::response([
            'has_more' => false,
            'data' => [['id' => 'in_3']],
        ]),
        'api.stripe.com/v1/invoices*' => Http::response([
            'has_more' => true,
            'data' => [['id' => 'in_1'], ['id' => 'in_2']],
        ]),
    ]);

    $seen = [];

    Invoice::open()->limit(2)->cursor()->each(function (Invoice $invoice) use (&$seen): void {
        $seen[] = $invoice->getKey();
    });

    expect($seen)->toBe(['in_1', 'in_2', 'in_3']);

    Http::assertSentCount(2);
});
