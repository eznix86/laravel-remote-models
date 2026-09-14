<?php

declare(strict_types=1);

namespace RemoteModels\Tests\Fixtures\Factories;

use RemoteModels\Factories\RemoteFactory;
use RemoteModels\Tests\Fixtures\Remote\Stripe\Customer;

/**
 * @extends RemoteFactory<Customer>
 */
class CustomerFactory extends RemoteFactory
{
    /**
     * @var class-string<Customer>
     */
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'cus_'.$this->faker->unique()->bothify('??????????'),
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'livemode' => false,
        ];
    }
}
