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
                    <span class="badge {{ $ticket->statusBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->status) }}</span>
                    <span class="badge {{ $ticket->priorityBadgeClass() }}">{{ \Illuminate\Support\Str::headline($ticket->priority) }}</span>
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
                    <p class="text-sm text-slate-500">There are no conversations yet.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h3 class="text-xl font-black">Reply</h3>
            @if($ticket->isClosed())
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                    The ticket is <span class="font-semibold text-slate-800">closed</span>, so new comments cannot be added.
                </div>
            @else
                <form method="POST" action="{{ route('tickets.messages.store', $ticket) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <textarea class="field min-h-36" name="body" placeholder="Write a reply. Mention user with format @username"></textarea>
                    <input class="field file:mr-4 file:rounded-2xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:font-semibold file:text-white file:transition hover:file:bg-blue-700" type="file" name="attachments[]" multiple>
                    @if(! auth()->user()->isClient())
                        <label class="flex items-center gap-3 text-sm text-slate-500"><input type="checkbox" name="is_internal" value="1"> Internal note</label>
                    @endif
                    <button class="btn-primary" type="submit">Send update</button>
                </form>
            @endif
        </div>

        <div class="panel">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-xl font-black">Audit Trail</h3>
                    <!-- <p class="mt-1 text-sm text-slate-500">Tampilkan 3 history terbaru terlebih dulu, lalu buka dropdown untuk melihat seluruh perubahan.</p> -->
                </div>
                <!-- <span class="badge bg-slate-100 text-slate-700">{{ $ticket->activityLogs->count() }} history</span> -->
            </div>
            @php
                $allLogs = $ticket->activityLogs->sortByDesc('created_at')->values();
                $latestLogs = $allLogs->take(3);
            @endphp
            <div class="mt-4 space-y-3">
                @forelse($latestLogs as $log)
                    <div class="rounded-2xl bg-slate-100 p-4 text-sm">
                        <p class="font-semibold">{{ $log->description }}</p>
                        <p class="text-slate-500">{{ $log->user?->name ?? 'System' }} - {{ $log->created_at->format('d M Y H:i') }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada audit log.</p>
                @endforelse
            </div>

            @if($allLogs->count() > 3)
                <details class="mt-4 rounded-3xl border border-slate-200 bg-slate-50/80 p-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-slate-700">
                        <span>Lihat seluruh history</span>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500 shadow-sm">{{ $allLogs->count() }} items</span>
                    </summary>
                    <div class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                        @foreach($allLogs as $log)
                            <div class="rounded-2xl bg-white p-4 text-sm shadow-sm ring-1 ring-slate-100">
                                <p class="font-semibold">{{ $log->description }}</p>
                                <p class="text-slate-500">{{ $log->user?->name ?? 'System' }} - {{ $log->created_at->format('d M Y H:i') }}</p>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="panel">
            <h3 class="text-xl font-black">Ticket Details</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Requester</dt><dd>{{ $ticket->requester?->name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Assignee</dt><dd>{{ $ticket->assignee?->name ?? 'Unassigned' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Project</dt><dd>{{ $ticket->team?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Affected device</dt><dd>{{ $ticket->device?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Category</dt><dd>{{ $ticket->subcategory?->name ?? $ticket->category?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Response due</dt><dd class="{{ $ticket->responseDueClass() }}">{{ $ticket->response_due_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Resolution due</dt><dd class="{{ $ticket->resolutionDueClass() }}">{{ $ticket->resolution_due_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Created</dt><dd>{{ $ticket->created_at->format('d M Y H:i') }}</dd></div>
            </dl>
        </div>

        @if(! auth()->user()->isClient())
            <div class="panel">
                <h3 class="text-xl font-black">Update Workflow</h3>
                @if($ticket->isClosed())
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                        The ticket is <span class="font-semibold text-slate-800">closed</span>, so the workflow cannot be updated anymore.
                    </div>
                @else
                    <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="mt-4 grid gap-4">
                        @csrf
                        @method('PATCH')
                        <label class="label">Status <span class="text-rose-500">*</span></label>
                        <select class="field" name="status" required>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected($ticket->status === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                            @endforeach
                        </select>
                        <label class="label">Priority <span class="text-rose-500">*</span></label>
                        <select class="field" name="priority" required>
                            @foreach($priorities as $priority)
                                <option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                            @endforeach
                        </select>
                        <label class="label">Assign to</label>
                        <select class="field" name="assigned_to" data-ticket-assignee>
                            <option value="" disabled hidden @selected(blank($ticket->assigned_to))>Select assignee</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}" @selected($ticket->assigned_to === $agent->id)>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                        <label class="label">Project <span class="text-rose-500">*</span></label>
                        <select class="field" name="team_id" data-ticket-project required>
                            <option value="" disabled hidden @selected(blank($ticket->team_id))>Select project</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" data-clients="{{ $project->members->where('role', 'client')->pluck('id')->implode(',') }}" data-agents="{{ $project->members->whereIn('role', ['agent', 'supervisor', 'admin'])->pluck('id')->implode(',') }}" data-devices="{{ $project->devices->pluck('id')->implode(',') }}" @selected($ticket->team_id === $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                        <label class="label">Affected Device <span class="text-rose-500">*</span></label>
                        <select class="field" name="device_id" data-ticket-device required>
                            <option value="" disabled hidden @selected(blank($ticket->device_id))>Select affected device</option>
                            @foreach($devices as $device)
                                <option value="{{ $device->id }}" data-project="{{ $device->team_id }}" @selected($ticket->device_id === $device->id)>{{ $device->name }}{{ $device->serial_number ? ' | '.$device->serial_number : '' }}</option>
                            @endforeach
                        </select>
                        <label class="label">Category</label>
                        <select class="field" name="category_id" data-ticket-category>
                            <option value="" disabled hidden @selected(blank($ticket->category_id))>Select category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected($ticket->category_id === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <label class="label">Subcategory</label>
                        <select class="field" name="subcategory_id" data-ticket-subcategory>
                            <option value="" disabled hidden @selected(blank($ticket->subcategory_id))>Select subcategory</option>
                            @foreach($categories as $category)
                                @foreach($category->children as $child)
                                    <option value="{{ $child->id }}" @selected($ticket->subcategory_id === $child->id)>{{ $category->name }} / {{ $child->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        <label class="label">Requester <span class="text-rose-500">*</span></label>
                        <select class="field" name="requester_id" data-ticket-requester required>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected($ticket->requester_id === $client->id)>{{ $client->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn-primary" type="submit">Save changes</button>
                    </form>
                @endif
            </div>

            @unless($ticket->isClosed())
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
            @endunless
        @endif


    </div>
</div>
@endsection
