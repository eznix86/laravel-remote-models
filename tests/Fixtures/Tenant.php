<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, mixed>
     */
    public function toRemoteConnection(): array
    {
        return [
            'url' => 'https://tenant.test',
            'token' => 'tenant-token',
        ];
    }
}
