<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Company;
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
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'description' => null,
        ];
    }
}
