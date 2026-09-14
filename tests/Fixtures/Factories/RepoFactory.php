<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Factories;

use RemoteModels\Factories\RemoteFactory;
use RemoteModels\Tests\Fixtures\Remote\Github\Repo;

/**
 * @extends RemoteFactory<Repo>
 */
class RepoFactory extends RemoteFactory
{
    /**
     * @var class-string<Repo>
     */
    protected $model = Repo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'repo-'.$this->faker->unique()->numberBetween(1, 100000);

        return [
            'full_name' => 'acme/'.$name,
            'name' => $name,
            'private' => false,
            'topics' => ['php'],
        ];
    }
}
