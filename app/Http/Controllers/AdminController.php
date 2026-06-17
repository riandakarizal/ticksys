<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Device;
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
        $user = Auth::user();
        $isAdmin = $user->canManageAllTickets();
        $companyId = $user->company_id;

        // Admin sees all records across tenants; others are scoped to their own tenant.
        $scope = fn (string $model) => $isAdmin
            ? $model::query()
            : $model::query()->where('company_id', $companyId);

        $users = $scope(User::class)
            ->with('teams:id,name')
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'supervisor' THEN 1 WHEN 'agent' THEN 2 WHEN 'client' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->get();

        $projects = $scope(Team::class)
            ->with(['members:id,name,role', 'lead:id,name'])
            ->orderBy('name')
            ->get();

        return [
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'users' => $users,
            'projects' => $projects,
            'categories' => $scope(Category::class)->with(['children', 'parent'])->orderBy('name')->get(),
            'slaPolicies' => $scope(SlaPolicy::class)->orderByDesc('is_default')->orderBy('name')->get(),
            'devices' => $scope(Device::class)
                ->with('team:id,name')
                ->withExists(['tickets as has_open_ticket' => fn ($query) => $query->where('status', '!=', 'closed')])
                ->orderBy('name')
                ->get(),
            'projectUsers' => $users->where('role', '!=', 'admin')->values(),
            'assignableAgents' => $users->whereIn('role', ['admin', 'supervisor', 'agent'])->values(),
            'coordinators' => $users->whereIn('role', ['admin', 'supervisor'])->values(),
        ];
    }

    protected function respond(Request $request, string $message, string $redirect): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect' => $redirect,
            ]);
        }

        return redirect($redirect)->with('success', $message);
    }

    protected function ensureCompanyRecord(object $model): void
    {
        if (Auth::user()->canManageAllTickets()) {
            return;
        }
        abort_unless($model->company_id === Auth::user()->company_id, 404);
    }

    protected function ensureProjectAssignmentForRole(string $role, array $projectIds): void
    {
        if ($role !== 'admin' && empty($projectIds)) {
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
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->count();

        return self::CATEGORY_COLOR_PALETTE[$parentCount % count(self::CATEGORY_COLOR_PALETTE)];
    }
}