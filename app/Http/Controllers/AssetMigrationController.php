<?php

namespace App\Http\Controllers;

use App\Models\AstMain;
use App\Models\AstMigration;
use App\Models\PjctMain;
use App\Models\User;
use App\Support\AssetImportService;
use App\Support\AssetMigrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Project → Migrasi Aset. Route dibatasi superadmin/admin; di sini dipersempit ke
 * `User::canMigrateAssets()` dan project di divisi user.
 */
class AssetMigrationController extends Controller
{
    public function __construct(private AssetMigrationService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $this->authorizeMigration($user);

        $sources = $this->service->sourceProjects($user);
        $from = null;
        $assets = collect();
        $targets = collect();

        if ($fromId = $request->query('from')) {
            $from = PjctMain::visibleTo($user)->find($fromId);

            if ($from) {
                $assets = AstMain::query()
                    ->where('ast_pjctid', $from->id)
                    ->orderBy('id')
                    ->get(['id', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_serial', 'ast_username',
                        'ast_userloc', 'ast_cond', 'ast_stat', 'ast_last_ticket_id']);
                $targets = $this->service->targetProjects($user, $from->id);
            }
        }

        $history = $this->scopedHistory($user)->with('user:id,user_name')->latest('id')->limit(20)->get();
        $projectNames = PjctMain::withTrashed()->pluck('pjct_name', 'id');
        $statuses = AssetImportService::STATUSES;

        return view('project.asset_migration', compact(
            'sources', 'from', 'assets', 'targets', 'history', 'projectNames', 'statuses'
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorizeMigration($user);

        $data = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string', 'different:from'],
            'ast_ids' => ['required', 'array', 'min:1'],
            'ast_ids.*' => ['string'],
            'stat_after' => ['nullable', Rule::in(AssetImportService::STATUSES)],
            'clear_location' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'to.required' => 'Pilih project tujuan.',
            'to.different' => 'Project tujuan harus berbeda dengan project asal.',
            'ast_ids.required' => 'Pilih minimal satu aset untuk dipindah.',
            'ast_ids.min' => 'Pilih minimal satu aset untuk dipindah.',
            'stat_after.in' => 'Status aset tidak valid.',
            'note.max' => 'Catatan maksimal 1000 karakter.',
        ]);

        // Asal & tujuan wajib di divisi user — project di luar itu diperlakukan seperti tidak ada.
        $from = PjctMain::visibleTo($user)->find($data['from']);
        $to = PjctMain::visibleTo($user)->find($data['to']);
        abort_unless($from && $to, 403);

        $migration = $this->service->migrate(
            $user,
            $from,
            $to,
            $data['ast_ids'],
            $data['stat_after'] ?? null,
            (bool) ($data['clear_location'] ?? false),
            $data['note'] ?? null,
        );

        return redirect()
            ->route('project.assets.migration.show', $migration)
            ->with('success', sprintf('%d aset dipindah dari %s ke %s.', $migration->asset_count, $from->id, $to->id));
    }

    public function show(Request $request, AstMigration $migration)
    {
        $user = $request->user();
        $this->authorizeMigration($user);
        abort_unless($this->scopedHistory($user)->whereKey($migration->id)->exists(), 403);

        $migration->load(['user:id,user_name', 'fromProject', 'toProject', 'items']);
        $assets = AstMain::query()
            ->whereIn('id', $migration->items->pluck('ast_id')->all())
            ->get(['id', 'ast_type', 'ast_brand', 'ast_brandmodel', 'ast_serial', 'ast_username',
                'ast_userloc', 'ast_cond', 'ast_stat', 'ast_pjctid'])
            ->keyBy('id');

        return view('project.asset_migration_show', compact('migration', 'assets'));
    }

    /** Riwayat yang asal atau tujuannya ada di divisi user (superadmin: semua). */
    private function scopedHistory(User $user)
    {
        $ids = $user->visibleProjectIds();

        return AstMigration::query()->when($ids !== null, fn ($q) => $q->where(
            fn ($w) => $w->whereIn('from_pjct_id', $ids)->orWhereIn('to_pjct_id', $ids)
        ));
    }

    private function authorizeMigration(User $user): void
    {
        abort_unless($user->canMigrateAssets(), 403);
    }
}
