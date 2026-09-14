<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RemoteModels\Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

function lastRequest(): Request
{
    $recorded = Http::recorded()->last();

    throw_unless(is_array($recorded), RuntimeException::class, 'No request was recorded.');

    return $recorded[0];
}

function lastUrl(): string
{
    return urldecode(lastRequest()->url());
}
