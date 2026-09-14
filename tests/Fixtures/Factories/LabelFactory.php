<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Factories;

use RemoteModels\Factories\RemoteFactory;
use RemoteModels\Tests\Fixtures\Remote\Github\Label;

/**
 * @extends RemoteFactory<Label>
 */
class LabelFactory extends RemoteFactory
{
    /**
     * @var class-string<Label>
     */
    protected $model = Label::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'label-'.$this->faker->unique()->numberBetween(1, 100000),
            'color' => 'ff0000',
        ];
    }
}
