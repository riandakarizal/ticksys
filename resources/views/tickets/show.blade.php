@extends('layouts.app', ['title' => $ticket->ticket_number, 'heading' => $ticket->ticket_number])

@section('content')
<div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
    <div class="space-y-6">
        <div class="panel">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-slate-500">{{ $ticket->ticket_number }}</p>
                    <h2 class="mt-2 text-3xl font-black">{{ $ticket->subject }}</h2>
                    <p class="mt-3 text-slate-600">{{ $ticket->description }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="badge bg-sky-100 text-sky-700">{{ \Illuminate\Support\Str::headline($ticket->status) }}</span>
                    <span class="badge bg-amber-100 text-amber-700">{{ \Illuminate\Support\Str::headline($ticket->priority) }}</span>
                    @if($ticket->isResolutionBreached())
                        <span class="badge bg-rose-100 text-rose-700">SLA breached</span>
                    @endif
                </div>
            </div>

            @if($ticket->tags)
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach($ticket->tags as $tag)
                        <span class="badge bg-slate-100 text-slate-700">#{{ $tag }}</span>
                    @endforeach
                </div>
            @endif

            @if($ticket->attachments->count())
                <div class="mt-6">
                    <p class="mb-3 text-sm font-semibold">Attachments</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($ticket->attachments as $attachment)
                            <a class="btn-soft" href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}">{{ $attachment->original_name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="panel">
            <h3 class="text-xl font-black">Conversation</h3>
            <div class="mt-6 space-y-4">
                @forelse($ticket->messages as $message)
                    @if(! $message->is_internal || ! auth()->user()->isClient())
                        <div class="rounded-3xl {{ $message->is_internal ? 'bg-amber-50' : 'bg-slate-100' }} p-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold">{{ $message->user?->name ?? 'Deleted user' }}</p>
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ $message->is_internal ? 'Internal note' : 'Reply' }}</p>
                                </div>
                                <p class="text-sm text-slate-500">{{ $message->created_at->format('d M Y H:i') }}</p>
                            </div>
                            <div class="mt-4 whitespace-pre-line text-sm leading-7">{{ $message->body }}</div>
                            @if($message->attachments->count())
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach($message->attachments as $attachment)
                                        <a class="btn-soft" href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}">{{ $attachment->original_name }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                @empty
                    <p class="text-sm text-slate-500">Belum ada percakapan tambahan.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h3 class="text-xl font-black">Reply</h3>
            <form method="POST" action="{{ route('tickets.messages.store', $ticket) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <textarea class="field min-h-36" name="body" placeholder="Tulis balasan. Mention user dengan format @namapengguna"></textarea>
                <input class="field" type="file" name="attachments[]" multiple>
                @if(! auth()->user()->isClient())
                    <label class="flex items-center gap-3 text-sm text-slate-500"><input type="checkbox" name="is_internal" value="1"> Internal note</label>
                @endif
                <button class="btn-primary" type="submit">Send update</button>
            </form>
        </div>

        <div class="panel">
            <h3 class="text-xl font-black">Audit Trail</h3>
            <div class="mt-4 space-y-3">
                @forelse($ticket->activityLogs as $log)
                    <div class="rounded-2xl bg-slate-100 p-4 text-sm">
                        <p class="font-semibold">{{ $log->description }}</p>
                        <p class="text-slate-500">{{ $log->user?->name ?? 'System' }} - {{ $log->created_at->format('d M Y H:i') }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada audit log.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="panel">
            <h3 class="text-xl font-black">Ticket Details</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Requester</dt><dd>{{ $ticket->requester?->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Assignee</dt><dd>{{ $ticket->assignee?->name ?? 'Unassigned' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Project</dt><dd>{{ $ticket->team?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Category</dt><dd>{{ $ticket->subcategory?->name ?? $ticket->category?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Response due</dt><dd class="{{ $ticket->responseDueClass() }}">{{ $ticket->response_due_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Resolution due</dt><dd class="{{ $ticket->resolutionDueClass() }}">{{ $ticket->resolution_due_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Created</dt><dd>{{ $ticket->created_at->format('d M Y H:i') }}</dd></div>
            </dl>
        </div>

        @if(! auth()->user()->isClient())
            <div class="panel">
                <h3 class="text-xl font-black">Update Workflow</h3>
                <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="mt-4 grid gap-4">
                    @csrf
                    @method('PATCH')
                    <select class="field" name="status">
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected($ticket->status === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                        @endforeach
                    </select>
                    <select class="field" name="priority">
                        @foreach($priorities as $priority)
                            <option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                        @endforeach
                    </select>
                    <select class="field" name="assigned_to">
                        <option value="" disabled hidden @selected(blank($ticket->assigned_to))>Select assignee</option>
                        @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" @selected($ticket->assigned_to === $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                    <select class="field" name="team_id">
                        <option value="" disabled hidden @selected(blank($ticket->team_id))>Select project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected($ticket->team_id === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                    <select class="field" name="category_id">
                        <option value="" disabled hidden @selected(blank($ticket->category_id))>Select category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($ticket->category_id === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <select class="field" name="subcategory_id">
                        <option value="" disabled hidden @selected(blank($ticket->subcategory_id))>Select subcategory</option>
                        @foreach($categories as $category)
                            @foreach($category->children as $child)
                                <option value="{{ $child->id }}" @selected($ticket->subcategory_id === $child->id)>{{ $category->name }} / {{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <select class="field" name="requester_id">
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected($ticket->requester_id === $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn-primary" type="submit">Save changes</button>
                </form>
            </div>

            <div class="panel">
                <h3 class="text-xl font-black">Automation Tools</h3>
                <form method="POST" action="{{ route('tickets.merge', $ticket) }}" class="mt-4 space-y-3">
                    @csrf
                    <select class="field" name="target_ticket_id">
                        <option value="" disabled hidden>Select merge target</option>
                        @foreach($mergeTargets as $mergeTarget)
                            <option value="{{ $mergeTarget->id }}">{{ $mergeTarget->ticket_number }} - {{ $mergeTarget->subject }}</option>
                        @endforeach
                    </select>
                    <button class="btn-soft" type="submit">Merge duplicate</button>
                </form>
                <form method="POST" action="{{ route('tickets.split', $ticket) }}" class="mt-6 space-y-3">
                    @csrf
                    <input class="field" name="subject" placeholder="Subject for split ticket">
                    <textarea class="field min-h-28" name="description" placeholder="Describe the split scope"></textarea>
                    <button class="btn-soft" type="submit">Split into new ticket</button>
                </form>
            </div>
        @endif


    </div>
</div>
@endsection

