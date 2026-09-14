<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RemoteModels\Concerns\InteractsWithRemoteModels;
use RemoteModels\Relations\HasManyRemote;
use RemoteModels\Tests\Fixtures\Helpdesk\Ticket;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;
use RemoteModels\Tests\Fixtures\Remote\Stripe\Customer;
use RemoteModels\Tests\Fixtures\Remote\Stripe\Invoice;

/**
 * @property string|null $github_token
 * @property string|null $stripe_id
 */
class User extends Model
{
    use InteractsWithRemoteModels;

    protected $guarded = [];

    public $timestamps = false;

    public function customers(): HasManyRemote
    {
        return $this->hasManyRemote(Customer::class);
    }

    public function invoices(): HasManyRemote
    {
        return $this->hasManyRemote(Invoice::class, 'customer', 'stripe_id');
    }

    public function tickets(): HasManyRemote
    {
        return $this->hasManyRemote(Ticket::class, 'user_id');
    }

    public function repos(): HasManyRemote
    {
        return $this->hasManyRemote(Repo::class)
            ->on(fn (self $user): array => [
                'url' => 'https://api.github.com',
                'token' => $user->github_token,
            ]);
    }
}
