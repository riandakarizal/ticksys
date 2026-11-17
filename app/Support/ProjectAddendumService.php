<?php

namespace App\Support;

use App\Models\PjctAddendum;
use App\Models\PjctDoc;
use App\Models\PjctMain;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat addendum kontrak dan langsung menerapkannya ke project (keputusan user: langsung berlaku,
 * tanpa persetujuan). Satu transaksi: nomor urut addendum, dokumen PDF (`pjct_doc` jenis ADD),
 * baris `pjct_addenda`, dan update kolom `pjct_main` yang terdampak.
 */
class ProjectAddendumService
{
    /**
     * @param  array{number: string, addendum_date: string, reasons: array<int, string>, notes?: array<string, ?string>,
     *               new_value?: int|string|null, new_end?: string|null, new_client?: string|null}  $data  sudah lolos validasi request
     *
     * @throws ValidationException bila nilai baru sama dengan nilai sekarang
     */
    public function record(User $user, PjctMain $project, array $data, UploadedFile $document): PjctAddendum
    {
        $reasons = array_values(array_intersect(array_keys(PjctAddendum::REASONS), $data['reasons']));
        $notes = collect($data['notes'] ?? [])
            ->only($reasons)
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn ($n) => $n !== '')
            ->all();

        $writtenPath = null;

        try {
            return DB::transaction(function () use ($user, $project, $data, $reasons, $notes, $document, &$writtenPath): PjctAddendum {
                // Kunci baris project: dua addendum bersamaan tidak boleh dapat nomor urut sama
                // atau saling menimpa nilai "sebelum".
                $project = PjctMain::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

                $changes = $this->changes($project, $reasons, $data);
                $seq = (int) PjctAddendum::query()->where('pjct_id', $project->id)->max('seq') + 1;

                $disk = Storage::disk('docfile');
                if (! $disk->exists($project->id)) {
                    $disk->makeDirectory($project->id);
                }
                $filename = PjctDoc::freeFilename($project->id, $document->getClientOriginalName());
                $writtenPath = $project->id.'/'.$filename;
                $disk->putFileAs($project->id, $document, $filename);

                $doc = PjctDoc::create([
                    'doc_number' => $data['number'],
                    'doc_pjctid' => $project->id,
                    'doc_type' => 'ADD',
                    'doc_filetype' => 'pdf',
                    'doc_filename' => $filename,
                    'doc_filepath' => $writtenPath,
                    'doc_desc' => "Addendum {$seq} — {$data['number']}",
                ]);

                $addendum = PjctAddendum::create([
                    'pjct_id' => $project->id,
                    'seq' => $seq,
                    'number' => $data['number'],
                    'addendum_date' => $data['addendum_date'],
                    'reasons' => $reasons,
                    'notes' => $notes ?: null,
                    'changes' => $changes ?: null,
                    'doc_id' => $doc->id,
                    'user_id' => $user->id,
                ]);

                if ($changes) {
                    // Lewat Eloquent → log `update` PjctMain (kolom yang berubah) ikut tercatat.
                    $project->update(array_map(fn ($c) => $c['after'], $changes));
                }

                return $addendum;
            });
        } catch (\Throwable $e) {
            if ($writtenPath !== null) {
                Storage::disk('docfile')->delete($writtenPath);
            }

            throw $e;
        }
    }

    /**
     * Perubahan kolom project untuk alasan yang terpilih: { kolom: { before, after } }.
     *
     * @param  array<int, string>  $reasons
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function changes(PjctMain $project, array $reasons, array $data): array
    {
        $changes = [];

        if (in_array('NILAI', $reasons, true)) {
            $after = (int) $data['new_value'];
            if ($after === (int) $project->pjct_value) {
                throw ValidationException::withMessages(['new_value' => 'Nilai kontrak baru sama dengan nilai sekarang.']);
            }
            $changes['pjct_value'] = ['before' => (int) $project->pjct_value, 'after' => $after];
        }

        if (in_array('WAKTU', $reasons, true)) {
            $after = Carbon::parse($data['new_end'])->toDateString();
            $before = $project->pjct_coend_m?->toDateString();
            if ($after === $before) {
                throw ValidationException::withMessages(['new_end' => 'Tanggal akhir kontrak baru sama dengan yang sekarang.']);
            }
            $changes['pjct_coend_m'] = ['before' => $before, 'after' => $after];
        }

        $client = trim((string) ($data['new_client'] ?? ''));
        if (in_array('IDENTITAS', $reasons, true) && $client !== '' && $client !== (string) $project->pjct_client) {
            $changes['pjct_client'] = ['before' => $project->pjct_client, 'after' => $client];
        }

        return $changes;
    }
}
