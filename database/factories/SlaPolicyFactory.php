<?php

namespace Database\Factories;

use App\Models\SlaPolicy;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    protected $model = SlaPolicy::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'response_minutes' => 60,
            'resolution_minutes' => 240,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
