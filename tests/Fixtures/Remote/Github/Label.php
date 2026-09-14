<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Github;

use RemoteModels\Attributes\Endpoint;
use RemoteModels\Concerns\HasRemoteFactory;
use RemoteModels\RemoteModel;
use RemoteModels\Tests\Fixtures\Factories\LabelFactory;

/**
 * @use HasRemoteFactory<LabelFactory>
 */
#[Endpoint('labels', key: 'name', keyType: 'string')]
class Label extends RemoteModel
{
    /** @use HasRemoteFactory<LabelFactory> */
    use HasRemoteFactory;

    protected static string $factory = LabelFactory::class;
}
