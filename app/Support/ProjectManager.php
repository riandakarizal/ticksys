<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectManager
{
    public function create(int $companyId, array $data): Team
    {
        $payload = $this->payload($companyId, $data);

        $project = Team::create($payload['attributes']);
        $project->members()->sync($payload['member_ids']);

        return $project->load(['members', 'lead']);
    }

    public function update(Team $project, array $data): Team
    {
        $payload = $this->payload($project->company_id, $data);

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

    private function payload(int $companyId, array $data): array
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
                'company_id' => $companyId,
                'name' => $data['name'],
                'code' => $data['code'] ?: Str::upper(Str::slug($data['name'], '-')),
                'description' => $data['description'] ?? null,
                'lead_user_id' => $leadUserId,
            ],
            'member_ids' => $memberIds->all(),
        ];
    }
}
