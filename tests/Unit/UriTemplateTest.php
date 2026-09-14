<?php

declare(strict_types=1);

use RemoteModels\Exceptions\UnresolvedUri;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;
use RemoteModels\UriTemplate;

it('sees which uris hold a placeholder', function (): void {
    expect(UriTemplate::isTemplate('/repos/{key}/issues'))->toBeTrue()
        ->and(UriTemplate::isTemplate('/repos/laravel/framework/issues'))->toBeFalse();
});

it('expands the key of the model', function (): void {
    $repo = (new Repo)->forceFill(['full_name' => 'laravel/framework']);

    expect(UriTemplate::expand('/repos/{key}/issues', $repo))->toBe('/repos/laravel/framework/issues')
        ->and(UriTemplate::expand('/repos/{id}', $repo))->toBe('/repos/laravel/framework');
});

it('prefers the key it is handed over the key of the model', function (): void {
    $repo = (new Repo)->forceFill(['full_name' => 'laravel/framework']);

    expect(UriTemplate::expand('/repos/{key}', $repo, 'laravel/pint'))->toBe('/repos/laravel/pint');
});

it('expands a dotted attribute', function (): void {
    $repo = (new Repo)->forceFill(['owner' => ['login' => 'laravel']]);

    expect(UriTemplate::expand('/orgs/{owner.login}/repos', $repo))->toBe('/orgs/laravel/repos');
});

it('leaves a uri without placeholders alone', function (): void {
    expect(UriTemplate::expand('/user/repos', new Repo))->toBe('/user/repos');
});

it('throws when a placeholder has no value', function (): void {
    UriTemplate::expand('/repos/{key}/issues', new Repo);
})->throws(UnresolvedUri::class, 'has no value for [key]');
