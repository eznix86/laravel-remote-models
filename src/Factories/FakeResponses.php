<?php

declare(strict_types=1);

namespace RemoteModels\Factories;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FakeResponses
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $records = [];

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected array $collections = [];

    protected bool $listening = false;

    /**
     * @param  array<string, mixed>  $record
     */
    public function record(string $indexUrl, string $showUrl, array $record): void
    {
        $this->listen();

        $this->records[$showUrl] = $record;

        $this->collections[$indexUrl][] = $record;
    }

    public function respond(Request $request): ?PromiseInterface
    {
        $url = Str::before($request->url(), '?');

        if (isset($this->records[$url])) {
            return Http::response($this->records[$url]);
        }

        if (isset($this->collections[$url])) {
            return Http::response($this->collections[$url]);
        }

        return null;
    }

    protected function listen(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        Http::fake(fn (Request $request): ?PromiseInterface => $this->respond($request));
    }
}
