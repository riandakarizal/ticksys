<?php

namespace App\Support;

use App\Http\Requests\Ticket\TicketStoreRequest;
use App\Http\Requests\Ticket\TicketUpdateRequest;
use App\Models\Category;
use App\Models\PjctMain;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketManager
{
    /**
     * Transisi status per aksi: status asal → status tujuan, plus jenis dokumen yang diunggah.
     * Hak akses tiap aksi ada di TicketPolicy (ability = nama aksi), aturan input di
     * TicketTransitionRequest.
     */
    public const TRANSITIONS = [
        'solve' => ['from' => 'open',      'to' => 'solved',    'doc_type' => 'penyelesaian',    'label' => 'Ditandai selesai (solved)'],
        'escalate' => ['from' => 'open',      'to' => 'escalated', 'doc_type' => 'ba_eskalasi',     'label' => 'Dieskalasi ke tim pusat'],
        'return' => ['from' => 'escalated', 'to' => 'open',      'doc_type' => 'ba_pengembalian', 'label' => 'Dikembalikan dari tim pusat ke teknisi'],
        'approve' => ['from' => 'solved',    'to' => 'closed',    'doc_type' => null,              'label' => 'Disetujui atasan, ticket ditutup'],
        'reject' => ['from' => 'solved',    'to' => 'open',      'doc_type' => null,              'label' => 'Ditolak atasan, kembali ke teknisi'],
    ];

    public function __construct(private readonly Helpdesk $helpdesk) {}

    public function createTicket(User $user, TicketStoreRequest $request): Ticket
    {
        $customFields = $this->helpdesk->defaultCustomFields();
        $this->validateCategories($request);
        $this->validateCustomFields($request, $customFields);

        $project = PjctMain::findOrFail($request->validated('pjct_id'));

        $ticket = DB::transaction(function () use ($request, $user, $customFields, $project): Ticket {
            $ticket = Ticket::create([
                // Client tidak punya akun PRISM — namanya diambil dari project.
                'requester_id' => null,
                'requester_name' => $project->pjct_client,
                'reporter_name' => $request->validated('reporter_name'),
                'created_by' => $user->id,
                'assigned_to' => $user->id,
                'pjct_id' => $project->id,
                'category_id' => $request->validated('category_id'),
                'subcategory_id' => $request->validated('subcategory_id'),
                'sla_policy_id' => SlaPolicy::query()->where('is_default', true)->value('id'),
                'subject' => $request->validated('subject'),
                'description' => $request->validated('description'),
                'issue_type' => $request->validated('issue_type'),
                'priority' => $request->validated('priority'),
                'status' => 'open',
                // Teknisi sudah menangani di lokasi saat tiket dibuat — tidak ada jeda respons.
                'first_responded_at' => now(),
            ]);

            $this->helpdesk->syncCustomFields($ticket, $customFields, $request->input('custom_fields', []));
            $ticket->loadMissing('slaPolicy');
            $this->helpdesk->applySlaDeadlines($ticket, $ticket->slaPolicy);
            $ticket->save();

            $documents = $this->helpdesk->storeAttachments($ticket, $request->file('documents', []), $user->id, 'kronologi');
            $this->helpdesk->syncTicketAssets($ticket, $request->validated('ast_ids') ?? []);

            $this->helpdesk->recordActivity($ticket, $user, 'ticket_created', 'Ticket dibuat', [
                'to' => 'open',
                'priority' => $ticket->priority,
                'assets' => $ticket->assets()->pluck('ast_main.id')->all(),
                'documents' => $documents,
            ]);

            return $ticket;
        });

        $this->helpdesk->notifyUsers(
            [$this->helpdesk->approverFor($ticket)],
            $ticket,
            'ticket_created',
            'Ticket '.$ticket->ticket_number.' dibuat',
            $user->user_name.' membuat ticket baru: '.$ticket->subject,
            ['ticket_id' => $ticket->id],
            $user,
        );

        return $ticket->load(['project', 'assets']);
    }

    /** Ubah detail tiket (prioritas, jenis masalah, kategori, device) selama belum closed. */
    public function updateDetails(Ticket $ticket, TicketUpdateRequest $request, User $user): Ticket
    {
        $this->validateCategories($request);

        $before = $ticket->only(['priority', 'issue_type', 'category_id', 'subcategory_id']);

        DB::transaction(function () use ($ticket, $request, $user, $before): void {
            $ticket->fill($request->safe()->only(['priority', 'issue_type', 'category_id', 'subcategory_id']));
            $ticket->save();

            $assetChanges = $this->helpdesk->syncTicketAssets($ticket, $request->validated('ast_ids') ?? []);

            $this->helpdesk->recordActivity($ticket, $user, 'ticket_updated', 'Detail ticket diperbarui', [
                'before' => $before,
                'after' => $ticket->only(array_keys($before)),
                'assets_added' => $assetChanges['attached'],
                'assets_removed' => $assetChanges['detached'],
            ]);
        });

        return $ticket;
    }

    /** Jalankan satu aksi alur (solve / escalate / return / approve / reject). */
    public function transition(Ticket $ticket, string $action, Request $request, User $user): Ticket
    {
        $transition = self::TRANSITIONS[$action];

        if ($ticket->status !== $transition['from']) {
            throw ValidationException::withMessages([
                'ticket' => 'Aksi ini hanya bisa dilakukan saat ticket berstatus '.Ticket::STATUS_LABELS[$transition['from']].'.',
            ]);
        }

        $note = trim((string) $request->input('note', ''));

        DB::transaction(function () use ($ticket, $action, $transition, $request, $user, $note): void {
            $from = $ticket->status;
            $ticket->status = $transition['to'];

            match ($action) {
                'solve' => $ticket->resolved_at = now(),
                'reject' => $ticket->resolved_at = null,
                'approve' => $ticket->forceFill(['closed_at' => now(), 'approved_by' => $user->id]),
                default => null,
            };

            $ticket->save();

            $documents = $transition['doc_type']
                ? $this->helpdesk->storeAttachments($ticket, $request->file('documents', []), $user->id, $transition['doc_type'])
                : [];

            if ($action === 'approve') {
                $this->helpdesk->releaseTicketAssets($ticket);
            }

            $this->helpdesk->recordActivity($ticket, $user, 'ticket_'.$action, $transition['label'], array_filter([
                'from' => $from,
                'to' => $ticket->status,
                'note' => $note !== '' ? $note : null,
                'documents' => $documents ?: null,
            ]));
        });

        $this->notifyTransition($ticket, $action, $user, $note);

        return $ticket;
    }

    private function notifyTransition(Ticket $ticket, string $action, User $actor, string $note): void
    {
        $ticket->loadMissing(['creator.parent', 'project']);
        $number = $ticket->ticket_number;
        $approver = $this->helpdesk->approverFor($ticket);

        [$recipients, $title, $message] = match ($action) {
            'solve' => [
                // Tanpa atasan tercatat, superadmin menjadi approver cadangan.
                $approver ? [$approver] : User::query()->where('user_role', 'superadmin')->get(),
                'Ticket '.$number.' menunggu approval',
                $actor->user_name.' menandai ticket selesai. Mohon review dan approve.',
            ],
            'escalate' => [
                $this->helpdesk->divisionTeam($ticket)->push($approver),
                'Ticket '.$number.' dieskalasi ke tim pusat',
                $actor->user_name.' mengeskalasi ticket — BA serah terima ke pusat terlampir.',
            ],
            'return' => [
                [$ticket->creator, $approver],
                'Ticket '.$number.' dikembalikan dari tim pusat',
                $actor->user_name.' mengembalikan unit ke teknisi — lanjutkan penyelesaian ticket.',
            ],
            'approve' => [
                [$ticket->creator],
                'Ticket '.$number.' disetujui dan ditutup',
                $actor->user_name.' menyetujui penyelesaian ticket.',
            ],
            'reject' => [
                [$ticket->creator],
                'Ticket '.$number.' ditolak',
                $actor->user_name.' menolak penyelesaian ticket: '.Str::limit($note, 150),
            ],
        };

        $this->helpdesk->notifyUsers($recipients, $ticket, 'ticket_'.$action, $title, $message, [
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
        ], $actor);
    }

    private function validateCategories(Request $request): void
    {
        $categoryId = $request->input('category_id');
        $subcategoryId = $request->input('subcategory_id');

        if ($subcategoryId && ! $categoryId) {
            throw ValidationException::withMessages(['subcategory_id' => 'Pilih kategori utama sebelum memilih subkategori.']);
        }

        if ($subcategoryId) {
            $subcategory = Category::query()->find($subcategoryId);

            if (! $subcategory || (int) $subcategory->parent_id !== (int) $categoryId) {
                throw ValidationException::withMessages(['subcategory_id' => 'Subkategori tidak cocok dengan kategori yang dipilih.']);
            }
        }
    }

    private function validateCustomFields(Request $request, Collection $customFields): void
    {
        foreach ($customFields as $field) {
            if ($field->is_required && blank($request->input('custom_fields.'.$field->key))) {
                throw ValidationException::withMessages(['custom_fields.'.$field->key => $field->name.' wajib diisi.']);
            }
        }
    }
}
