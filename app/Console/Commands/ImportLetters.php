<?php

namespace App\Console\Commands;

use App\Models\Letter;
use App\Models\User;
use App\Support\LetterImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Impor register nomor surat lama (Excel) ke tabel `letters`. Jalankan dengan --dry-run dulu.
 * Di server: salin file ke container (`docker compose cp`) lalu jalankan lewat `docker compose exec app`.
 */
#[Signature('letters:import {path : File .xlsx register nomor surat} {--dry-run : Hanya tampilkan ringkasan, tidak menulis ke database} {--replace : Hapus data hasil impor sebelumnya lalu impor ulang}')]
#[Description('Impor register nomor surat lama dari Excel')]
class ImportLetters extends Command
{
    public function handle(LetterImportService $importer): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $already = Letter::query()->where('source', 'import')->count();
        if ($already && ! $this->option('replace') && ! $this->option('dry-run')) {
            $this->error("Sudah ada {$already} nomor hasil impor. Pakai --replace untuk mengimpor ulang (nomor yang dibuat di PRISM tidak tersentuh).");

            return self::FAILURE;
        }

        $this->info('Membaca '.basename($path).' ...');
        ['rows' => $rows, 'skipped' => $skipped] = $importer->parse($path);

        $this->table(
            ['Jenis surat', 'Jumlah', 'Bertanda'],
            collect($rows)->groupBy('type_name')->sortKeys()
                ->map(fn ($g, $name) => [$name, $g->count(), $g->filter(fn ($r) => $r['notes'])->count() ?: ''])
                ->values()->all()
        );

        $flagged = collect($rows)->filter(fn ($r) => $r['notes']);
        $this->line(sprintf('Akan diimpor: <info>%d</info> nomor · bertanda: <comment>%d</comment> · dilewati: <comment>%d</comment>', count($rows), $flagged->count(), count($skipped)));

        if ($skipped) {
            $this->newLine();
            $this->line('<comment>Baris yang dilewati:</comment>');
            $this->table(['Sheet', 'Baris', 'Nomor', 'Alasan'], array_map(fn ($s) => [$s['sheet'], $s['row'], $s['number'], $s['reason']], $skipped));
        }

        if ($flagged->isNotEmpty() && $this->output->isVerbose()) {
            $this->table(['Sheet', 'Baris', 'Nomor', 'Catatan'], $flagged->map(fn ($r) => [$r['sheet'], $r['row'], $r['number'], implode(' · ', $r['notes'])])->all());
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — tidak ada yang ditulis. Tambahkan -v untuk melihat semua baris bertanda.');

            return self::SUCCESS;
        }

        $userId = User::query()->where('user_role', 'superadmin')->orderBy('id')->value('id');
        $result = $importer->execute($rows, $userId, (bool) $this->option('replace'));

        $this->info(sprintf('Selesai: %d nomor diimpor, %d counter kode/tahun diperbarui.', $result['inserted'], $result['counters']));

        return self::SUCCESS;
    }
}
