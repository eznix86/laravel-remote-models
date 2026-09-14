<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RemoteModels\Tests\Fixtures\Remote\Rpc\Person;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('posts the filters to a list action', function (): void {
    Http::fake(['rpc.test/api/users/list' => Http::response([['id' => 1, 'name' => 'Taylor']])]);

    expect(Person::where('status', 'active')->get())->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://rpc.test/api/users/list'
        && $request->data() === ['status' => 'active']);
});

it('posts the key to a read action', function (): void {
    Http::fake(['rpc.test/api/users/get' => Http::response(['id' => 7, 'name' => 'Taylor'])]);

    expect(Person::find(7)->name)->toBe('Taylor');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://rpc.test/api/users/get'
        && $request->data() === ['id' => 7]);
});

it('posts the attributes to a create action', function (): void {
    Http::fake(['rpc.test/api/users/create' => Http::response(['id' => 7, 'name' => 'Taylor'])]);

    expect(Person::create(['name' => 'Taylor'])->getKey())->toBe(7);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://rpc.test/api/users/create'
        && $request->data() === ['name' => 'Taylor']);
});

it('posts the key and the dirty attributes to an update action', function (): void {
    Http::fake([
        'rpc.test/api/users/get' => Http::response(['id' => 7, 'name' => 'Taylor']),
        'rpc.test/api/users/update' => Http::response(['id' => 7, 'name' => 'Nuno']),
    ]);

    $person = Person::find(7);

    $person->update(['name' => 'Nuno']);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://rpc.test/api/users/update'
        && $request->data() === ['id' => 7, 'name' => 'Nuno']);
});

it('posts the key to a delete action', function (): void {
    Http::fake([
        'rpc.test/api/users/get' => Http::response(['id' => 7, 'name' => 'Taylor']),
        'rpc.test/api/users/delete' => Http::response(['deleted' => true]),
    ]);

    $person = Person::find(7);

    expect($person->delete())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://rpc.test/api/users/delete'
        && $request->data() === ['id' => 7]);
});

it('sends a custom action through the model client', function (): void {
    Http::fake([
        'rpc.test/api/users/get' => Http::response(['id' => 7]),
        'rpc.test/api/users/reset-password' => Http::response(['sent' => true]),
    ]);

    expect(Person::find(7)->resetPassword()->json('sent'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://rpc.test/api/users/reset-password'
        && $request->data() === ['id' => 7]
        && $request->hasHeader('Authorization', 'Bearer rpc-token'));
});

it('sends a custom action that carries its own payload', function (): void {
    Http::fake([
        'rpc.test/api/users/get' => Http::response(['id' => 7]),
        'rpc.test/api/users/send-email' => Http::response(['queued' => true]),
    ]);

    Person::find(7)->sendEmail('Welcome');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://rpc.test/api/users/send-email'
        && $request->data() === ['id' => 7, 'subject' => 'Welcome']);
});
