<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectManager
{
    public function create(int $tenantId, array $data): Team
    {
        $payload = $this->payload($tenantId, $data);

        $project = Team::create($payload['attributes']);
        $project->members()->sync($payload['member_ids']);

        return $project->load(['members', 'lead']);
    }

    public function update(Team $project, array $data): Team
    {
        $payload = $this->payload($project->tenant_id, $data);

        $project->update($payload['attributes']);
        $project->members()->sync($payload['member_ids']);

        return $project->load(['members', 'lead']);
    }

    public function delete(Team $project): void
    {
        $project->categories()->detach();
        $project->members()->detach();
        $project->delete();
    }

    private function payload(int $tenantId, array $data): array
    {
        $memberIds = collect($data['member_ids'] ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        $leadUserId = ! empty($data['lead_user_id']) ? (int) $data['lead_user_id'] : null;

        if ($leadUserId) {
            $memberIds->push($leadUserId);
        }

        $memberIds = $memberIds->unique()->values();

        if ($memberIds->isEmpty()) {
            throw ValidationException::withMessages([
                'member_ids' => 'Project wajib memiliki minimal satu member.',
            ]);
        }

        return [
            'attributes' => [
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'code' => $data['code'] ?: Str::upper(Str::slug($data['name'], '-')),
                'description' => $data['description'] ?? null,
                'lead_user_id' => $leadUserId,
            ],
            'member_ids' => $memberIds->all(),
        ];
    }
}
