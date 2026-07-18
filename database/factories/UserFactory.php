<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'id'          => strtoupper(Str::random(8)),
            'user_empid'  => strtoupper('EMP' . fake()->unique()->numerify('####')),
            'user_name'   => fake()->name(),
            'user_email'  => fake()->unique()->safeEmail(),
            'user_pass'   => static::$password ??= Hash::make('password'),
            'user_level'  => fake()->randomElement(['L3', 'L4', 'L5', 'L6', 'L7']),
            'user_role'   => 'user',
            'user_unit'   => fake()->randomElement(['FM', 'IT', 'HR', 'Finance', 'Operations']),
            'user_div'    => fake()->randomElement(['Airport', 'Industrial', 'Commercial']),
            'user_parid'  => '-',
            'user_status' => 'active',
        ];
    }

    public function admin(): static
    {
        return $this->state(['user_role' => 'superadmin', 'user_level' => 'L1']);
    }

    public function supervisor(): static
    {
        return $this->state(['user_role' => 'admin', 'user_level' => 'L2']);
    }

    public function agent(): static
    {
        return $this->state(['user_role' => 'siteadmin', 'user_level' => 'L5']);
    }

    public function client(): static
    {
        return $this->state(['user_role' => 'user', 'user_level' => 'L7']);
    }

    public function vip(): static
    {
        return $this->state(['user_role' => 'vip', 'user_level' => 'L1']);
    }

    public function inactive(): static
    {
        return $this->state(['user_status' => 'inactive']);
    }
}
