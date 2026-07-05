<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SlaPolicy;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private const CATEGORY_COLOR_PALETTE = [
        '#2563eb',
        '#0f766e',
        '#7c3aed',
        '#ea580c',
        '#0891b2',
        '#16a34a',
        '#be185d',
        '#4f46e5',
    ];

    protected function viewData(string $pageTitle, string $pageDescription): array
    {
        $users = User::query()
            ->with('teams:id,name')
            ->orderByRaw("CASE user_role
                WHEN 'admin'      THEN 0
                WHEN 'supervisor' THEN 1
                WHEN 'agent'      THEN 2
                WHEN 'client'     THEN 3
                WHEN 'vip'        THEN 4
                ELSE 5 END")
            ->orderBy('user_name')
            ->get();

        $projects = Team::query()
            ->with(['members:id,user_name,user_role', 'lead:id,user_name'])
            ->orderBy('name')
            ->get();

        return [
            'pageTitle'        => $pageTitle,
            'pageDescription'  => $pageDescription,
            'users'            => $users,
            'projects'         => $projects,
            'categories'       => Category::query()->with(['children', 'parent'])->orderBy('name')->get(),
            'slaPolicies'      => SlaPolicy::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'devices'          => collect(),
            'projectUsers'     => $users->where('user_role', '!=', 'admin')->values(),
            'assignableAgents' => $users->whereIn('user_role', ['admin', 'supervisor', 'agent'])->values(),
            'coordinators'     => $users->whereIn('user_role', ['admin', 'supervisor'])->values(),
        ];
    }

    protected function respond(Request $request, string $message, string $redirect): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message'  => $message,
                'redirect' => $redirect,
            ]);
        }

        return redirect($redirect)->with('success', $message);
    }

    protected function ensureCompanyRecord(object $model): void
    {
        // Single-tenant — no company scoping needed.
    }

    protected function ensureProjectAssignmentForRole(string $role, array $projectIds): void
    {
        if ($role !== 'admin' && $role !== 'vip' && empty($projectIds)) {
            throw ValidationException::withMessages([
                'project_ids' => 'Non-admin users must be assigned to at least one project.',
            ]);
        }
    }

    protected function parseDeviceCsv(string $path): Collection
    {
        $rows = collect();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return $rows;
        }

        $headers = null;
        $knownHeaders = ['name', 'asset_code', 'device_type', 'serial_number', 'ip_address', 'location', 'notes'];

        while (($data = fgetcsv($handle)) !== false) {
            $data = array_map(fn ($value) => trim((string) $value), $data);

            if (collect($data)->filter()->isEmpty()) {
                continue;
            }

            if ($headers === null) {
                $normalized = array_map(fn ($value) => strtolower(str_replace([' ', '-'], '_', $value)), $data);
                if (array_intersect($normalized, $knownHeaders)) {
                    $headers = $normalized;
                    continue;
                }

                $headers = ['name', 'asset_code', 'device_type', 'serial_number', 'ip_address', 'location', 'notes'];
            }

            $rows->push(array_replace(array_fill_keys($headers, null), array_combine(array_slice($headers, 0, count($data)), $data)));
        }

        fclose($handle);

        return $rows;
    }

    protected function resolveCategoryColor(int $companyId, ?string $color, ?Category $parentCategory = null, ?string $fallbackColor = null): string
    {
        if ($parentCategory) {
            return $parentCategory->color ?: $this->defaultCategoryColor($companyId);
        }

        if (filled($color)) {
            return $color;
        }

        if (filled($fallbackColor)) {
            return $fallbackColor;
        }

        return $this->defaultCategoryColor($companyId);
    }

    protected function defaultCategoryColor(int $companyId): string
    {
        $parentCount = Category::query()
            ->whereNull('parent_id')
            ->count();

        return self::CATEGORY_COLOR_PALETTE[$parentCount % count(self::CATEGORY_COLOR_PALETTE)];
    }
}
