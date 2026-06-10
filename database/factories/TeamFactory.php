<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'description' => null,
        ];
    }
}
