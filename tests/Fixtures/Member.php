<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RemoteModels\Attributes\BelongsToLocal;
use RemoteModels\Attributes\HasManyRemote;
use RemoteModels\Concerns\InteractsWithRemoteModels;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;

#[HasManyRemote(Repo::class)]
#[BelongsToLocal(User::class, remoteKey: 'owner.login', as: 'owner')]
class Member extends Model
{
    use InteractsWithRemoteModels;

    protected $guarded = [];

    public $timestamps = false;
}
