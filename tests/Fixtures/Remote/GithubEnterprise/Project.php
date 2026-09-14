<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\GithubEnterprise;

use RemoteModels\Attributes\Endpoint;
use RemoteModels\RemoteModel;

#[Endpoint('/projects')]
class Project extends RemoteModel {}
