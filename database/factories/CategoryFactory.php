<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'color' => '#' . fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
