<?php

namespace Database\Factories;

use App\Models\PjctMain;
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
            'created_by' => User::factory()->client(),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'issue_type' => 'sistem',
            'status' => 'open',
            'priority' => 'medium',
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => 'open']);
    }

    public function escalated(): static
    {
        return $this->state(['status' => 'escalated']);
    }

    public function solved(): static
    {
        return $this->state(['status' => 'solved', 'resolved_at' => now()]);
    }

    /** Ticket dibuat (dan ditangani) oleh teknisi ini. */
    public function byTechnician(User $user): static
    {
        return $this->state(['created_by' => $user->id, 'assigned_to' => $user->id]);
    }

    public function forProject(PjctMain $project): static
    {
        return $this->state(['pjct_id' => $project->id, 'requester_name' => $project->pjct_client]);
    }
}
