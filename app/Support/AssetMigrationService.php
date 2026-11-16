<?php

namespace App\Support;

use App\Models\AstMain;
use App\Models\AstMigration;
use App\Models\AstMigrationItem;
use App\Models\PjctMain;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Migrasi aset antar-project (Project → Migrasi Aset). Dipakai saat kontrak project habis / tidak
 * diperpanjang dan unitnya dipakai lagi di project baru.
 *
 * Aturan:
 * - asal & tujuan harus di divisi user (`PjctMain::visibleTo()`), tujuan ≠ asal, tujuan bukan END;
 * - aset yang masih ada di ticket aktif (`ast_last_ticket_id`) tidak boleh dipindah — ticket-nya
 *   menempel ke project asal, jadi selesaikan dulu ticket-nya;
 * - riwayat disimpan di `ast_migrations` + `ast_migration_items` (snapshot kolom yang mungkin ditimpa),
 *   plus satu baris `system_logs` per batch. `ast_main` di-update massal (tanpa event per aset).
 *
 * `ast_main` dan `pjct_main` beda collation di MySQL, jadi tidak ada JOIN antar keduanya — selalu lewat
 * daftar ID.
 */
class AssetMigrationService
{
    /** Kolom "pengguna & lokasi" yang dikosongkan bila opsi itu dipilih. NOT NULL di skema legacy → ''. */
    public const LOCATION_COLUMNS = ['ast_username', 'ast_userreg', 'ast_userloc', 'ast_userlocdet'];

    /**
     * Project asal: project di divisi user yang punya aset, project END (kontrak habis) di atas.
     * Tiap item diberi atribut `asset_count`.
     *
     * @return Collection<int, PjctMain>
     */
    public function sourceProjects(User $user): Collection
    {
        $counts = AstMain::query()
            ->whereNotNull('ast_pjctid')
            ->selectRaw('ast_pjctid, COUNT(*) as c')
            ->groupBy('ast_pjctid')
            ->pluck('c', 'ast_pjctid');

        // Kunci hitungan dinormalisasi: data lama bisa beda huruf besar/kecil dengan pjct_main.id.
        $counts = $counts->mapWithKeys(fn ($c, $id) => [strtoupper((string) $id) => (int) $c]);

        return PjctMain::visibleTo($user)
            ->whereIn('id', $counts->keys()->all())
            ->get(['id', 'pjct_name', 'pjct_status', 'pjct_div', 'pjct_contract'])
            ->each(fn (PjctMain $p) => $p->setAttribute('asset_count', $counts[strtoupper($p->id)] ?? 0))
            ->sortBy(fn (PjctMain $p) => [$p->pjct_status === 'END' ? 0 : 1, $p->id])
            ->values();
    }

    /**
     * Project tujuan: di divisi user, bukan project asal, belum berakhir (END) dan tidak diarsip.
     *
     * @return Collection<int, PjctMain>
     */
    public function targetProjects(User $user, string $exceptId): Collection
    {
        return PjctMain::visibleTo($user)
            ->where('id', '!=', $exceptId)
            ->where(fn ($q) => $q->whereNull('pjct_status')->orWhere('pjct_status', '!=', 'END'))
            ->orderBy('id')
            ->get(['id', 'pjct_name', 'pjct_status', 'pjct_div', 'pjct_contract']);
    }

    /**
     * Pindahkan aset terpilih dari `$from` ke `$to`. Semua-atau-tidak-sama-sekali: kalau satu saja
     * aset sudah tidak memenuhi syarat (pindah duluan, ticket baru dibuka), tidak ada yang dipindah.
     *
     * @param  array<int, string>  $assetIds
     *
     * @throws ValidationException
     */
    public function migrate(
        User $user,
        PjctMain $from,
        PjctMain $to,
        array $assetIds,
        ?string $statAfter = null,
        bool $clearLocation = false,
        ?string $note = null,
    ): AstMigration {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to' => 'Project tujuan harus berbeda dengan project asal.']);
        }

        if ($to->pjct_status === 'END') {
            throw ValidationException::withMessages(['to' => "Project tujuan {$to->id} sudah berakhir (END) — pilih project yang masih berjalan."]);
        }

        if ($statAfter !== null && ! in_array($statAfter, AssetImportService::STATUSES, true)) {
            throw ValidationException::withMessages(['stat_after' => "Status \"{$statAfter}\" tidak valid."]);
        }

        $assetIds = array_values(array_unique(array_map('strval', $assetIds)));

        if (! $assetIds) {
            throw ValidationException::withMessages(['ast_ids' => 'Pilih minimal satu aset untuk dipindah.']);
        }

        return DB::transaction(function () use ($user, $from, $to, $assetIds, $statAfter, $clearLocation, $note): AstMigration {
            $assets = collect();
            foreach (array_chunk($assetIds, 500) as $chunk) {
                $assets = $assets->merge(AstMain::query()->whereIn('id', $chunk)->lockForUpdate()->get());
            }
            $assets = $assets->keyBy('id');

            $problems = [];
            foreach ($assetIds as $id) {
                $asset = $assets[$id] ?? null;

                if (! $asset) {
                    $problems[] = "{$id} tidak ditemukan";
                } elseif (strcasecmp((string) $asset->ast_pjctid, $from->id) !== 0) {
                    $problems[] = "{$id} bukan aset project {$from->id}";
                } elseif ($asset->ast_last_ticket_id !== null) {
                    $problems[] = "{$id} masih ada di ticket aktif";
                }
            }

            if ($problems) {
                $more = count($problems) > 5 ? ' (+'.(count($problems) - 5).' lainnya)' : '';

                throw ValidationException::withMessages([
                    'ast_ids' => 'Tidak ada aset yang dipindah — '.implode('; ', array_slice($problems, 0, 5)).$more.'. Muat ulang halaman lalu pilih lagi.',
                ]);
            }

            $migration = AstMigration::create([
                'from_pjct_id' => $from->id,
                'to_pjct_id' => $to->id,
                'user_id' => $user->id,
                'asset_count' => count($assetIds),
                'stat_after' => $statAfter,
                'cleared_location' => $clearLocation,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ]);

            // Insert massal tidak melewati cast `before` → json_encode manual.
            $items = array_map(fn (string $id) => [
                'ast_migration_id' => $migration->id,
                'ast_id' => $id,
                'before' => json_encode($assets[$id]->only(['ast_stat', ...self::LOCATION_COLUMNS]), JSON_UNESCAPED_UNICODE),
            ], $assetIds);

            foreach (array_chunk($items, 500) as $chunk) {
                AstMigrationItem::insert($chunk);
            }

            $update = ['ast_pjctid' => $to->id];
            if ($statAfter !== null) {
                $update['ast_stat'] = $statAfter;
            }
            if ($clearLocation) {
                $update += array_fill_keys(self::LOCATION_COLUMNS, '');
            }

            foreach (array_chunk($assetIds, 500) as $chunk) {
                AstMain::query()->whereIn('id', $chunk)->update($update);
            }

            // Satu baris log per batch, seperti import — satu baris per aset akan membanjiri log.
            SystemLog::create([
                'user_id' => $user->id,
                'action' => 'migrate',
                'loggable_type' => 'AstMigration',
                'loggable_id' => (string) $migration->id,
                'description' => sprintf('Migrasi %d aset %s → %s', count($assetIds), $from->id, $to->id),
                'properties' => [
                    'from' => $from->id,
                    'to' => $to->id,
                    'stat_after' => $statAfter,
                    'cleared_location' => $clearLocation,
                    'ids' => $assetIds,
                ],
            ]);

            return $migration;
        });
    }
}
