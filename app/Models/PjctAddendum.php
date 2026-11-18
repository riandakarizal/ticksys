<?php

namespace App\Models;

use App\Models\Concerns\LogsSystemActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu addendum kontrak sebuah project. Dibuat hanya lewat `ProjectAddendumService::record()`, yang
 * sekaligus menerapkan perubahannya ke `pjct_main`. Tidak diedit/dihapus — salah catat dikoreksi
 * dengan addendum berikutnya atau edit project.
 */
class PjctAddendum extends Model
{
    use LogsSystemActivity;

    protected $table = 'pjct_addenda';

    /** Kode alasan → [label, penjelasan]. Urutan = urutan tampil di form. */
    public const REASONS = [
        'NILAI' => ['Nilai kontrak', 'Penambahan atau pengurangan nilai pekerjaan.'],
        'WAKTU' => ['Jangka waktu kontrak', 'Perpanjangan masa pelaksanaan pekerjaan.'],
        'SPEK' => ['Spesifikasi barang', 'Perubahan tipe, merek, model, atau spesifikasi teknis.'],
        'VOLUME' => ['Volume pekerjaan', 'Penambahan atau pengurangan jumlah barang.'],
        'LINGKUP' => ['Ruang lingkup pekerjaan', 'Penambahan atau pengurangan lingkup pekerjaan.'],
        'JADWAL' => ['Jadwal pelaksanaan', 'Perubahan jadwal pengiriman atau penyelesaian pekerjaan.'],
        'BAYAR' => ['Mekanisme pembayaran', 'Perubahan termin atau tahapan pembayaran.'],
        'IDENTITAS' => ['Identitas perusahaan', 'Perubahan nama perusahaan atau alamat akibat perubahan data legal.'],
        'ADMIN' => ['Ketentuan administrasi', 'Perubahan dokumen atau persyaratan administratif.'],
        'LAINNYA' => ['Ketentuan lainnya', 'Perubahan klausul tertentu berdasarkan kesepakatan para pihak.'],
    ];

    /** Alasan yang mengubah kolom project → kolom `pjct_main`-nya. */
    public const FIELD_REASONS = [
        'NILAI' => 'pjct_value',
        'WAKTU' => 'pjct_coend_m',
        'IDENTITAS' => 'pjct_client',
    ];

    /** Alasan yang cukup diwakili nilai barunya — keterangan opsional. Selain ini keterangan wajib. */
    public const NOTE_OPTIONAL = ['NILAI', 'WAKTU'];

    /** Label kolom untuk tampilan perubahan. */
    public const FIELD_LABELS = [
        'pjct_value' => 'Nilai kontrak',
        'pjct_coend_m' => 'Akhir kontrak',
        'pjct_client' => 'Client',
    ];

    protected $fillable = [
        'pjct_id', 'seq', 'number', 'addendum_date', 'reasons', 'notes', 'changes', 'doc_id', 'user_id',
    ];

    protected $casts = [
        'seq' => 'integer',
        'addendum_date' => 'date',
        'reasons' => 'array',
        'notes' => 'array',
        'changes' => 'array',
    ];

    public static function reasonLabel(string $code): string
    {
        return self::REASONS[$code][0] ?? $code;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PjctMain::class, 'pjct_id')->withTrashed();
    }

    public function doc(): BelongsTo
    {
        return $this->belongsTo(PjctDoc::class, 'doc_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
