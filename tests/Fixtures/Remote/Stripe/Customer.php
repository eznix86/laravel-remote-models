<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Stripe;

use RemoteModels\Attributes\BelongsToLocal;
use RemoteModels\Attributes\BracketFilters;
use RemoteModels\Attributes\CacheFor;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\Paging;
use RemoteModels\Attributes\Persist;
use RemoteModels\Attributes\RemoteHasMany;
use RemoteModels\Concerns\HasRemoteFactory;
use RemoteModels\RemoteModel;
use RemoteModels\Tests\Fixtures\Factories\CustomerFactory;
use RemoteModels\Tests\Fixtures\User;

#[Endpoint('/v1/customers', key: 'id', keyType: 'string')]
#[Persist('/v1/customers/{key}', method: 'post')]
#[BracketFilters]
#[Paging(perPage: 'limit', page: null)]
#[CacheFor(300)]
#[RemoteHasMany(Invoice::class, as: 'invoices', foreignKey: 'customer')]
#[BelongsToLocal(User::class, remoteKey: 'metadata.user_id', ownerKey: 'id', as: 'user')]
class Customer extends RemoteModel
{
    /** @use HasRemoteFactory<CustomerFactory> */
    use HasRemoteFactory;

    protected static string $factory = CustomerFactory::class;

    protected $fillable = ['email', 'name', 'description', 'metadata'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'created' => 'immutable_datetime',
            'livemode' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
