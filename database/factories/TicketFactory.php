<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'team_id' => Team::factory(),
            'requester_id' => User::factory()->client(),
            'created_by' => User::factory()->client(),
            'category_id' => Category::factory(),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'open',
            'priority' => 'medium',
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => 'open']);
    }

    public function resolved(): static
    {
        return $this->state(['status' => 'resolved', 'resolved_at' => now()]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(['assigned_to' => $user->id]);
    }

    public function forTeam(Team $team): static
    {
        return $this->state(['team_id' => $team->id, 'tenant_id' => $team->tenant_id]);
    }

    public function forRequester(User $user): static
    {
        return $this->state([
            'requester_id' => $user->id,
            'created_by' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
    }
}
