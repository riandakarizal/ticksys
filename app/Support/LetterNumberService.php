<?php

namespace App\Support;

use App\Models\Letter;
use App\Models\LetterCounter;
use App\Models\LetterType;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalan membuat nomor surat.
 *
 * Urutan dihitung per KODE + TAHUN (bukan per jenis surat), reset tiap tahun, seperti register Excel.
 * Baris `letter_counters` untuk kode+tahun dikunci selama transaksi, jadi dua admin yang menyimpan
 * bersamaan antre dan mendapat nomor berbeda. Sebelum dipakai, urutan dicek ke tabel `letters`:
 * kalau sudah terpakai (mis. data impor Excel yang tahunnya salah ketik), nomor itu dilompati.
 */
class LetterNumberService
{
    /** Batas nomor sekali buat (register lama pernah membuat 50 nomor turunan sekaligus). */
    public const MAX_BATCH = 50;

    /**
     * Ambil $quantity nomor baru berurutan, mis. SKR/XI/2026/200, 201, ...
     *
     * @param  array<string, mixed>  $attributes  subject, signer, recipient, pic, pjct_id, file_*
     * @return Collection<int, Letter>
     */
    public function issue(LetterType $type, CarbonInterface $date, int $quantity, array $attributes, ?string $userId): Collection
    {
        $year = (int) $date->year;
        $month = (int) $date->month;

        return DB::transaction(function () use ($type, $date, $quantity, $attributes, $userId, $year, $month): Collection {
            $counter = $this->lockCounter($type->code, $year);
            $seq = $counter->last_seq;
            $letters = collect();

            for ($i = 0; $i < $quantity; $i++) {
                do {
                    $seq++;
                } while ($this->seqTaken($type->code, $year, $seq));

                $letters->push($this->create($type->id, $type->unit_code, $type->code, $year, $month, $seq, null, $date, $attributes, $userId));
            }

            $counter->update(['last_seq' => $seq]);

            return $letters;
        });
    }

    /**
     * Nomor turunan dari nomor yang sudah ada, mis. BAPP/II/2025/001 → 001.1, 001.2, ...
     * Bulan romawi mengikuti tanggal surat turunan (seperti di register Excel); tahun harus sama
     * dengan nomor induk karena urutannya per tahun.
     *
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, Letter>
     */
    public function issueSub(Letter $parent, CarbonInterface $date, int $quantity, array $attributes, ?string $userId): Collection
    {
        return DB::transaction(function () use ($parent, $date, $quantity, $attributes, $userId): Collection {
            // Kunci yang sama dengan nomor utama, supaya turunan untuk induk yang sama tidak bentrok.
            $this->lockCounter($parent->code, $parent->year);

            $sub = (int) Letter::query()
                ->where('code', $parent->code)
                ->where('year', $parent->year)
                ->where('seq', $parent->seq)
                ->max('sub_seq');

            $letters = collect();
            for ($i = 0; $i < $quantity; $i++) {
                $sub++;
                $letters->push($this->create(
                    $parent->letter_type_id, $parent->unit_code, $parent->code, $parent->year, (int) $date->month,
                    $parent->seq, $sub, $date, $attributes, $userId
                ));
            }

            return $letters;
        });
    }

    private function lockCounter(string $code, int $year): LetterCounter
    {
        LetterCounter::query()->insertOrIgnore([
            'code' => $code,
            'year' => $year,
            'last_seq' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return LetterCounter::query()
            ->where('code', $code)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function seqTaken(string $code, int $year, int $seq): bool
    {
        return Letter::query()->where('code', $code)->where('year', $year)->where('seq', $seq)->exists();
    }

    /** @param  array<string, mixed>  $attributes */
    private function create(
        int $typeId, string $unit, string $code, int $year, int $month, int $seq, ?int $sub,
        CarbonInterface $date, array $attributes, ?string $userId
    ): Letter {
        return Letter::create([
            ...$attributes,
            'letter_type_id' => $typeId,
            'number' => Letter::formatNumber($unit, $code, $month, $year, $seq, $sub),
            'unit_code' => $unit,
            'code' => $code,
            'year' => $year,
            'month' => $month,
            'seq' => $seq,
            'sub_seq' => $sub,
            'letter_date' => $date->toDateString(),
            'status' => Letter::STATUS_ACTIVE,
            'source' => 'app',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
