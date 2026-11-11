<?php

namespace App\Http\Controllers;

use App\Models\LetterCounter;
use App\Models\LetterType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Surat → Jenis Surat: master jenis surat (superadmin + admin).
 *
 * Mengubah kode hanya berlaku untuk nomor berikutnya — nomor yang sudah dibuat menyimpan kodenya sendiri.
 * Jenis yang sudah punya nomor tidak dihapus, cukup dinonaktifkan supaya tidak muncul di form.
 */
class LetterTypeController extends Controller
{
    public function index(): View
    {
        $types = LetterType::query()->withCount('letters')->orderBy('name')->get();
        $year = (int) now()->year;

        return view('letters.types', [
            'types' => $types,
            'year' => $year,
            'lastSeq' => LetterCounter::query()->where('year', $year)->pluck('last_seq', 'code'),
            'sharedCodes' => $types->groupBy('code')->filter(fn ($g) => $g->count() > 1)->map(fn ($g) => $g->pluck('name')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = LetterType::create($this->validated($request) + ['is_active' => true]);

        return back()->with('success', 'Jenis surat "'.$type->name.'" ditambahkan.');
    }

    public function update(Request $request, LetterType $letterType): RedirectResponse
    {
        $letterType->update($this->validated($request, $letterType) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Jenis surat "'.$letterType->name.'" disimpan.');
    }

    /** @return array<string, string> */
    private function validated(Request $request, ?LetterType $type = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'code' => strtoupper(trim((string) $request->input('code'))),
            'unit_code' => strtoupper(trim((string) $request->input('unit_code', 'DEO'))),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('letter_types', 'name')->ignore($type?->id)],
            'code' => ['required', 'regex:/^[A-Z0-9-]{1,20}$/'],
            'unit_code' => ['required', 'regex:/^[A-Z]{2,10}$/'],
        ], [
            'name.required' => 'Isi nama jenis surat.',
            'name.unique' => 'Nama jenis surat sudah ada.',
            'code.required' => 'Isi kode surat.',
            'code.regex' => 'Kode hanya huruf besar, angka, dan tanda minus (maks. 20), mis. SKR atau BA-SAT.',
            'unit_code.regex' => 'Kode unit hanya huruf (2–10), mis. DEO.',
        ]);
    }
}
