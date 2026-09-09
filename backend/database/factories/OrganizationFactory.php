<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();
        return [
            'name' => $name,
            'slug' => str($name)->slug().'-'.$this->faker->unique()->numberBetween(10, 99),
            'status' => 'active',
            'timezone' => 'Asia/Manila',
            'default_currency' => 'PHP',
        ];
    }
}
