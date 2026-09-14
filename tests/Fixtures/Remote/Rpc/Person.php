<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Rpc;

use Illuminate\Http\Client\Response;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\Index;
use RemoteModels\Attributes\Persist;
use RemoteModels\Attributes\Remove;
use RemoteModels\Attributes\Show;
use RemoteModels\Attributes\Store;
use RemoteModels\RemoteModel;

#[Endpoint('/api/users', key: 'id', keyType: 'int')]
#[Index('/api/users/list', method: 'post')]
#[Show('/api/users/get', method: 'post')]
#[Store('/api/users/create')]
#[Persist('/api/users/update', method: 'post')]
#[Remove('/api/users/delete', method: 'post')]
class Person extends RemoteModel
{
    protected $fillable = ['name', 'email'];

    public function resetPassword(): Response
    {
        return $this->newRemoteRequest()->post('/api/users/reset-password', [
            $this->getKeyName() => $this->getKey(),
        ]);
    }

    public function sendEmail(string $subject): Response
    {
        return $this->newRemoteRequest()->post('/api/users/send-email', [
            $this->getKeyName() => $this->getKey(),
            'subject' => $subject,
        ]);
    }
}
