{{-- Satu baris riwayat tiket. Props: $log (ActivityLog). Menangani log flow baru maupun log lama. --}}
@php
    $statusLabels = \App\Models\Ticket::STATUS_LABELS + ['in_progress' => 'In Progress', 'pending' => 'Pending', 'resolved' => 'Resolved'];
    $actionPills = [
        'ticket_created'      => ['bg-blue-100 text-blue-700',       'Dibuat'],
        'ticket_solve'        => ['bg-emerald-100 text-emerald-700', 'Solved'],
        'ticket_escalate'     => ['bg-violet-100 text-violet-700',   'Eskalasi'],
        'ticket_return'       => ['bg-blue-100 text-blue-700',       'Kembali dari Pusat'],
        'ticket_approve'      => ['bg-slate-200 text-slate-700',     'Approved'],
        'ticket_reject'       => ['bg-rose-100 text-rose-700',       'Ditolak'],
        'ticket_updated'      => ['bg-slate-200 text-slate-600',     'Diperbarui'],
        'reply_added'         => ['bg-sky-100 text-sky-700',         'Komentar'],
        'internal_note_added' => ['bg-amber-100 text-amber-700',     'Catatan'],
        'sla_breached'        => ['bg-red-100 text-red-700',         'SLA Breach'],
        'sla_escalated'       => ['bg-red-100 text-red-700',         'SLA Breach'],
        'ticket_auto_closed'  => ['bg-slate-200 text-slate-600',     'Auto Closed'],
        'ticket_merged'       => ['bg-slate-200 text-slate-600',     'Merged'],
        'ticket_split'        => ['bg-indigo-100 text-indigo-700',   'Split'],
    ];
    $props = $log->properties ?? [];
    $from = $props['from'] ?? null;
    $to = $props['to'] ?? null;
    $pill = $actionPills[$log->action] ?? null;
@endphp
<div class="rounded-2xl bg-slate-50 p-4 text-sm ring-1 ring-slate-100">
    <div class="flex flex-wrap items-center gap-1.5">
        @if($pill)
            <span class="badge {{ $pill[0] }}">{{ $pill[1] }}</span>
        @endif
        @if($to && in_array($log->action, ['ticket_status_changed', 'ticket_solve', 'ticket_escalate', 'ticket_return', 'ticket_approve', 'ticket_reject'], true))
            @if($from)
                <span class="badge {{ \App\Models\Ticket::statusBadgeClassFor($from) }}">{{ $statusLabels[$from] ?? ucfirst($from) }}</span>
                <span class="text-slate-400">→</span>
            @endif
            <span class="badge {{ \App\Models\Ticket::statusBadgeClassFor($to) }}">{{ $statusLabels[$to] ?? ucfirst($to) }}</span>
        @endif
    </div>
    <p class="mt-2 font-semibold text-slate-800">{{ $log->description }}</p>
    @if(!empty($props['note']))
        <p class="mt-1 whitespace-pre-line text-slate-600">{{ $props['note'] }}</p>
    @endif
    @if(!empty($props['documents']))
        <p class="mt-1 text-xs text-slate-500">Dokumen: {{ implode(', ', (array) $props['documents']) }}</p>
    @endif
    @if(!empty($props['assets_added']) || !empty($props['assets_removed']))
        <p class="mt-1 text-xs text-slate-500">
            @if(!empty($props['assets_added'])) Device ditambah: {{ implode(', ', $props['assets_added']) }}. @endif
            @if(!empty($props['assets_removed'])) Device dilepas: {{ implode(', ', $props['assets_removed']) }}. @endif
        </p>
    @endif
    <p class="mt-1 text-xs text-slate-500">{{ $log->user?->user_name ?? 'System' }} — {{ $log->created_at->format('d M Y H:i') }}</p>
</div>
