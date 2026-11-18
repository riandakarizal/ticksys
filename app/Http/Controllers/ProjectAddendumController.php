<?php

namespace App\Http\Controllers;

use App\Models\PjctAddendum;
use App\Models\PjctMain;
use App\Models\User;
use App\Support\ProjectAddendumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Addendum kontrak per project (Project → Main → tombol Addendum).
 * Lihat: siapa pun yang boleh melihat project-nya di Monitoring. Catat: superadmin + admin divisi
 * "Equipment & Technology Commercial" (sama dengan edit project / upload dokumen), dibatasi divisinya.
 */
class ProjectAddendumController extends Controller
{
    public function __construct(private ProjectAddendumService $service) {}

    public function index(Request $request, PjctMain $project): View
    {
        $user = $request->user();
        abort_unless($user->canSeeProject($project), 403);

        $addenda = PjctAddendum::query()
            ->where('pjct_id', $project->id)
            ->with(['doc:id,doc_filename', 'user:id,user_name'])
            ->orderByDesc('seq')
            ->get();

        // Format lama: addendum dicatat sebagai baris project terpisah (pjct_cotype/pjct_parcon).
        // Kolom pjct_parcon hanya ditambahkan di MySQL (lihat migration-nya), jadi dicek dulu.
        $hasLegacy = Schema::hasColumn('pjct_main', 'pjct_parcon');

        return view('monitoring.addenda', [
            'project' => $project,
            'addenda' => $addenda,
            'canManage' => self::canManage($user, $project),
            'legacyAddenda' => $hasLegacy
                ? $project->addendumProjects()->orderBy('id')->get(['id', 'pjct_contract', 'pjct_coend_m'])
                : collect(),
            'legacyParent' => $hasLegacy && $project->pjct_parcon
                ? $project->parentContract()->first(['id', 'pjct_name'])
                : null,
        ]);
    }

    public function store(Request $request, PjctMain $project): RedirectResponse
    {
        $user = $request->user();
        abort_unless(self::canManage($user, $project), 403);

        $reasons = (array) $request->input('reasons', []);
        $needsNote = fn (string $code) => in_array($code, $reasons, true) && ! in_array($code, PjctAddendum::NOTE_OPTIONAL, true);

        $rules = [
            'number' => ['required', 'string', 'max:255'],
            'addendum_date' => ['required', 'date'],
            'reasons' => ['required', 'array', 'min:1'],
            'reasons.*' => ['string', Rule::in(array_keys(PjctAddendum::REASONS))],
            'notes' => ['nullable', 'array'],
            'new_value' => [Rule::requiredIf(in_array('NILAI', $reasons, true)), 'nullable', 'integer', 'min:0'],
            'new_end' => [Rule::requiredIf(in_array('WAKTU', $reasons, true)), 'nullable', 'date'],
            'new_client' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:'.PjctDocController::MAX_KB],
        ];
        $messages = [
            'number.required' => 'Isi nomor addendum.',
            'addendum_date.required' => 'Isi tanggal addendum.',
            'reasons.required' => 'Pilih minimal satu alasan addendum.',
            'new_value.required' => 'Isi nilai kontrak baru.',
            'new_value.integer' => 'Nilai kontrak baru harus angka (Rupiah, tanpa titik/koma).',
            'new_end.required' => 'Isi tanggal akhir kontrak yang baru.',
            'document.required' => 'Unggah dokumen addendum (PDF).',
            'document.mimes' => 'Dokumen addendum harus PDF.',
            'document.max' => 'Dokumen addendum maksimal 5 MB.',
        ];
        foreach (array_keys(PjctAddendum::REASONS) as $code) {
            $rules["notes.{$code}"] = [Rule::requiredIf($needsNote($code)), 'nullable', 'string', 'max:2000'];
            $messages["notes.{$code}.required"] = 'Isi keterangan untuk alasan "'.PjctAddendum::reasonLabel($code).'".';
        }

        $data = $request->validate($rules, $messages);

        $addendum = $this->service->record($user, $project, $data, $request->file('document'));

        return redirect()
            ->route('monitoring.addenda.index', $project)
            ->with('success', "Addendum {$addendum->seq} ({$addendum->number}) tercatat untuk {$project->id}.");
    }

    /** Sama dengan edit project & upload dokumen: superadmin, atau admin E&T Commercial di divisinya. */
    public static function canManage(User $user, PjctMain $project): bool
    {
        if (! ($user->isSuperAdmin() || ($user->isAdmin() && $user->user_div === 'Equipment & Technology Commercial'))) {
            return false;
        }

        $allowedDivs = $user->allowedDivCodes();

        return $allowedDivs === null || in_array($project->pjct_div, $allowedDivs, true);
    }
}
