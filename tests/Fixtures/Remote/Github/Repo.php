<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Remote\Github;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Uri;
use RemoteModels\Attributes\Endpoint;
use RemoteModels\Attributes\Headers;
use RemoteModels\Attributes\Index;
use RemoteModels\Attributes\Store;
use RemoteModels\Concerns\HasRemoteFactory;
use RemoteModels\Relations\BelongsToLocal;
use RemoteModels\Relations\RemoteHasMany;
use RemoteModels\RemoteBuilder;
use RemoteModels\RemoteModel;
use RemoteModels\Tests\Fixtures\Factories\RepoFactory;
use RemoteModels\Tests\Fixtures\User;

/**
 * @property string $full_name
 * @property string|null $clone_url
 */
#[Endpoint('/repos', key: 'full_name', keyType: 'string')]
#[Index('/user/repos')]
#[Store('/user/repos')]
#[Headers(['X-Test' => 'repo'])]
class Repo extends RemoteModel
{
    /** @use HasRemoteFactory<RepoFactory> */
    use HasRemoteFactory;

    protected static string $factory = RepoFactory::class;

    protected $fillable = ['name', 'description', 'private'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'private' => 'boolean',
            'topics' => 'array',
            'owner' => Owner::class,
        ];
    }

    /**
     * @param  RemoteBuilder<self>  $query
     * @return RemoteBuilder<self>
     */
    protected function scopeVisibility(RemoteBuilder $query, string $visibility): RemoteBuilder
    {
        return $query->where('visibility', $visibility);
    }

    public function issues(): RemoteHasMany
    {
        return $this->remoteHasMany(Issue::class)->via('/repos/{key}/issues');
    }

    public function openIssues(): RemoteHasMany
    {
        return $this->remoteHasMany(Issue::class)->via('/repos/{key}/issues?state=open');
    }

    public function taggedIssues(): RemoteHasMany
    {
        return $this->remoteHasMany(Issue::class)->via(Uri::of('/issues')->withQuery(['filter' => 'tagged']));
    }

    public function inlineIssues(): RemoteHasMany
    {
        return $this->remoteHasMany(Issue::class)->via("/repos/{$this->full_name}/issues");
    }

    public function user(): BelongsToLocal
    {
        return $this->belongsToLocal(User::class, 'owner.login', 'github_login');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function cloneCommand(): Attribute
    {
        return Attribute::get(fn (): string => $this->clone_url === null ? '' : 'git clone '.$this->clone_url);
    }
}
