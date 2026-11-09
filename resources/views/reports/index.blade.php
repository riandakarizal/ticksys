@extends('layouts.app', ['title' => 'Reports', 'heading' => 'Reports & Analytics'])

@section('content')
<div class="grid gap-5 lg:grid-cols-4">
    <div class="stat"><p class="text-sm text-slate-500">Tickets</p><p class="mt-2 text-4xl font-black">{{ $ticketCount }}</p></div>
    <div class="stat"><p class="text-sm text-slate-500">Rata-rata sampai Solved</p><p class="mt-2 text-4xl font-black">{{ $averageResolutionMinutes }}m</p></div>
    <div class="stat"><p class="text-sm text-slate-500">SLA Breach Rate</p><p class="mt-2 text-4xl font-black">{{ $breachRate }}%</p></div>
    <div class="stat"><p class="text-sm text-slate-500">Export</p><a class="btn-primary mt-3" href="{{ route('reports.export', request()->query()) }}">Download CSV</a></div>
</div>

<div class="mt-6 panel">
    <form method="GET" class="grid gap-4 md:grid-cols-7">
        <input class="field" type="date" name="date_from" value="{{ request('date_from') }}">
        <input class="field" type="date" name="date_to" value="{{ request('date_to') }}">
        <select class="field" name="pjct_id"><option value="">Semua project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(request('pjct_id') === $project->id)>{{ $project->pjct_name }}</option>@endforeach</select>
        <select class="field" name="status"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
        <select class="field" name="issue_type"><option value="">Semua jenis</option>@foreach($issueTypes as $value => $label)<option value="{{ $value }}" @selected(request('issue_type') === $value)>{{ $label }}</option>@endforeach</select>
        <select class="field" name="priority"><option value="">Semua prioritas</option>@foreach(\App\Models\Ticket::PRIORITIES as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>@endforeach</select>
        <select class="field" name="category_id"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select>
        <button class="btn-primary md:col-span-7 md:w-max" type="submit">Terapkan</button>
    </form>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
    <div class="panel">
        <h2 class="text-xl font-black">Ticket per Jenis Masalah</h2>
        <div class="mt-4 space-y-3 text-sm">
            @forelse($byCategory as $label => $count)
                <div class="flex items-center justify-between rounded-2xl bg-slate-100 px-4 py-3"><span>{{ $label }}</span><span class="font-bold">{{ $count }}</span></div>
            @empty
                <p class="text-slate-500">Belum ada data.</p>
            @endforelse
        </div>
    </div>
    <div class="panel overflow-hidden">
        <h2 class="text-xl font-black">Hasil Filter</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-slate-500"><tr><th class="pb-3 pr-3">Ticket</th><th class="pb-3 pr-3">Project</th><th class="pb-3 pr-3">Jenis</th><th class="pb-3 pr-3">Status</th><th class="pb-3">Teknisi</th></tr></thead>
                <tbody class="divide-y divide-slate-200">
                    @foreach($tickets as $ticket)
                        <tr>
                            <td class="py-3 pr-3"><a class="font-semibold hover:text-blue-600" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_number }}</a><div class="text-slate-500">{{ $ticket->subject }}</div></td>
                            <td class="py-3 pr-3">{{ $ticket->project?->pjct_name ?? '-' }}<div class="text-slate-500">{{ $ticket->clientLabel() }}</div></td>
                            <td class="py-3 pr-3">{{ $ticket->issueTypeLabel() }}</td>
                            <td class="py-3 pr-3"><span class="badge {{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span></td>
                            <td class="py-3">{{ $ticket->creator?->user_name ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $tickets->links() }}</div>
    </div>
</div>
@endsection
