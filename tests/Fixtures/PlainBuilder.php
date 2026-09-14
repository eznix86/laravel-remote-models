<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class PlainBuilder extends Builder {}
