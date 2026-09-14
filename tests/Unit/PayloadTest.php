<?php

declare(strict_types=1);

use RemoteModels\Payload;

it('tells a list apart from a record', function (): void {
    expect(Payload::isList([1, 2]))->toBeTrue()
        ->and(Payload::isList([]))->toBeTrue()
        ->and(Payload::isList(['a' => 1]))->toBeFalse()
        ->and(Payload::isRecord(['a' => 1]))->toBeTrue()
        ->and(Payload::isRecord([1, 2]))->toBeFalse()
        ->and(Payload::isRecord('text'))->toBeFalse();
});

it('turns the keys of a decoded object into strings', function (): void {
    expect(Payload::record([0 => 'first', 'name' => 'Taylor']))
        ->toBe(['0' => 'first', 'name' => 'Taylor']);
});

it('answers an empty record for anything that is not an array', function (): void {
    expect(Payload::record(null))->toBeEmpty()
        ->and(Payload::record('text'))->toBeEmpty()
        ->and(Payload::record(7))->toBeEmpty();
});

it('maps a list of decoded objects', function (): void {
    expect(Payload::records([['id' => 1], ['id' => 2]]))
        ->toBe([['id' => 1], ['id' => 2]])
        ->and(Payload::records('text'))->toBeEmpty();
});

it('keeps only the strings of a header value', function (): void {
    expect(Payload::strings(['a', 1, null, 'b', ['c']]))->toBe(['a', 'b'])
        ->and(Payload::strings('text'))->toBeEmpty();
});
