<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Github;

use RemoteModels\Attributes\BelongsToLocal;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\Index;
use RemoteModels\Attributes\RemoteHasMany;
use RemoteModels\RemoteModel;
use RemoteModels\Tests\Fixtures\User;

#[Endpoint('/repos', key: 'full_name', keyType: 'string')]
#[Index('/user/starred')]
#[RemoteHasMany(Issue::class, as: 'issues', via: '/repos/{key}/issues')]
#[BelongsToLocal(User::class, remoteKey: 'owner.login', ownerKey: 'github_login', as: 'user')]
class Star extends RemoteModel {}
