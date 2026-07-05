@extends('layouts.app', ['title' => $ticket->ticket_number, 'heading' => $ticket->ticket_number])

@section('content')
<nav class="mb-4 flex items-center gap-1.5 text-sm" aria-label="Breadcrumb">
    <a href="{{ route('tickets.index') }}" class="text-slate-500 hover:text-slate-700">Tickets</a>
    <span class="text-slate-300">›</span>
    <span class="font-medium text-slate-900">{{ $ticket->ticket_number }}</span>
</nav>

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
                        <span class="badge bg-rose-100 text-rose-700">SLA Breached</span>
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
                                    <p class="font-semibold">{{ $message->user?->user_name ?? 'Deleted user' }}</p>
                                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ $message->is_internal ? 'Internal Note' : 'Reply' }}</p>
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
                    <p class="text-sm text-slate-500">No conversation yet.</p>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <h3 class="text-xl font-black">Reply</h3>
            @if($ticket->isClosed())
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                    This ticket is <span class="font-semibold text-slate-800">closed</span> — no new replies can be added.
                </div>
            @else
                <form method="POST" action="{{ route('tickets.messages.store', $ticket) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <textarea class="field min-h-36" name="body" placeholder="Write a reply. Mention users with @username"></textarea>
                    <input class="field file:mr-4 file:rounded-2xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:font-semibold file:text-white file:transition hover:file:bg-blue-700" type="file" name="attachments[]" multiple>
                    @if(! auth()->user()->isClient())
                        <label class="flex items-center gap-3 text-sm text-slate-500"><input type="checkbox" name="is_internal" value="1"> Internal note</label>
                    @endif
                    <button class="btn-primary" type="submit">Send</button>
                </form>
            @endif
        </div>

        <div class="panel">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-xl font-black">Activity Log</h3>
                </div>
            </div>
            @php
                $allLogs = $ticket->activityLogs->sortByDesc('created_at')->values();
                $latestLogs = $allLogs->take(3);
                $statusColors = [
                    'open'        => 'bg-blue-100 text-blue-700',
                    'in_progress' => 'bg-amber-100 text-amber-700',
                    'pending'     => 'bg-orange-100 text-orange-700',
                    'resolved'    => 'bg-emerald-100 text-emerald-700',
                    'closed'      => 'bg-slate-200 text-slate-700',
                ];
                $statusLabels = [
                    'open'        => 'Open',
                    'in_progress' => 'In Progress',
                    'pending'     => 'Pending',
                    'resolved'    => 'Resolved',
                    'closed'      => 'Closed',
                ];
                $actionPills = [
                    'ticket_created'       => ['bg-emerald-100 text-emerald-700', 'Ticket Dibuat'],
                    'reply_added'          => ['bg-blue-100 text-blue-700',       'Balasan'],
                    'internal_note_added'  => ['bg-violet-100 text-violet-700',   'Catatan Internal'],
                    'ticket_updated'       => ['bg-slate-200 text-slate-600',     'Diperbarui'],
                    'sla_escalated'        => ['bg-red-100 text-red-700',         'SLA Breach'],
                    'ticket_auto_closed'   => ['bg-slate-200 text-slate-600',     'Auto Closed'],
                    'ticket_merged'        => ['bg-slate-200 text-slate-600',     'Merged'],
                    'ticket_split'         => ['bg-indigo-100 text-indigo-700',   'Split'],
                ];
            @endphp
            <div class="mt-4 space-y-3">
                @forelse($latestLogs as $log)
                    <div class="rounded-2xl bg-slate-100 p-4 text-sm">
                        <p class="font-semibold">{{ $log->description }}</p>
                        @if ($log->action === 'ticket_status_changed' && !empty($log->properties['to']))
                            @php $from = $log->properties['from'] ?? null; $to = $log->properties['to']; @endphp
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                @if ($from)
                                    <span class="badge {{ $statusColors[$from] ?? 'bg-slate-100 text-slate-700' }}">{{ $statusLabels[$from] ?? ucfirst($from) }}</span>
                                    <span class="text-slate-400">→</span>
                                @endif
                                <span class="badge {{ $statusColors[$to] ?? 'bg-slate-100 text-slate-700' }}">{{ $statusLabels[$to] ?? ucfirst($to) }}</span>
                            </div>
                        @elseif (isset($actionPills[$log->action]))
                            <div class="mt-1.5">
                                <span class="badge {{ $actionPills[$log->action][0] }}">{{ $actionPills[$log->action][1] }}</span>
                            </div>
                        @endif
                        <p class="mt-1 text-slate-500">{{ $log->user?->user_name ?? 'System' }} — {{ $log->created_at->format('d M Y H:i') }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No activity log yet.</p>
                @endforelse
            </div>

            @if($allLogs->count() > 3)
                <details class="mt-4 rounded-3xl border border-slate-200 bg-slate-50/80 p-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-slate-700">
                        <span>View all history</span>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500 shadow-sm">{{ $allLogs->count() }} entries</span>
                    </summary>
                    <div class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                        @foreach($allLogs as $log)
                            <div class="rounded-2xl bg-white p-4 text-sm shadow-sm ring-1 ring-slate-100">
                                <p class="font-semibold">{{ $log->description }}</p>
                                @if ($log->action === 'ticket_status_changed' && !empty($log->properties['to']))
                                    @php $from = $log->properties['from'] ?? null; $to = $log->properties['to']; @endphp
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                        @if ($from)
                                            <span class="badge {{ $statusColors[$from] ?? 'bg-slate-100 text-slate-700' }}">{{ $statusLabels[$from] ?? ucfirst($from) }}</span>
                                            <span class="text-slate-400">→</span>
                                        @endif
                                        <span class="badge {{ $statusColors[$to] ?? 'bg-slate-100 text-slate-700' }}">{{ $statusLabels[$to] ?? ucfirst($to) }}</span>
                                    </div>
                                @elseif (isset($actionPills[$log->action]))
                                    <div class="mt-1.5">
                                        <span class="badge {{ $actionPills[$log->action][0] }}">{{ $actionPills[$log->action][1] }}</span>
                                    </div>
                                @endif
                                <p class="mt-1 text-slate-500">{{ $log->user?->user_name ?? 'System' }} — {{ $log->created_at->format('d M Y H:i') }}</p>
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
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Requester</dt><dd>{{ $ticket->requester?->user_name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Assigned To</dt><dd>{{ $ticket->assignee?->user_name ?? 'Unassigned' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Project</dt><dd>{{ $ticket->team?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Affected Device</dt><dd>{{ $ticket->device?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Category</dt><dd>{{ $ticket->subcategory?->name ?? $ticket->category?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Response Due</dt><dd class="{{ $ticket->responseDueClass() }}">{{ $ticket->response_due_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Resolution Due</dt><dd class="{{ $ticket->resolutionDueClass() }}">{{ $ticket->resolution_due_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Created</dt><dd>{{ $ticket->created_at->format('d M Y H:i') }}</dd></div>
            </dl>
        </div>

        @if(auth()->user()->isClient())
            <div class="panel">
                <h3 class="text-xl font-black">Handling Status</h3>
                <div class="mt-4 space-y-3">
                    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">
                        <p class="font-semibold">Support team is handling this ticket.</p>
                        <p class="mt-1 text-blue-700">You will be notified when there are updates.</p>
                    </div>
                    @if($ticket->assignee)
                        <div class="rounded-2xl bg-slate-100 px-4 py-3 text-sm">
                            <p class="text-slate-500">Handled by</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ $ticket->assignee->user_name }}</p>
                        </div>
                    @else
                        <div class="rounded-2xl bg-slate-100 px-4 py-3 text-sm text-slate-500">
                            Unassigned.
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if(! auth()->user()->isClient())
            <div class="panel">
                <h3 class="text-xl font-black">Update Ticket</h3>
                @if($ticket->isClosed())
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                        This ticket is <span class="font-semibold text-slate-800">closed</span> and can no longer be updated.
                    </div>
                @else
                    <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="mt-4 grid gap-4" id="update-workflow-form">
                        @csrf
                        @method('PATCH')
                        <label class="label">Status <span class="text-rose-500">*</span></label>
                        <select class="field" name="status" id="status-select" required>
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
                        <label class="label">Assign To</label>
                        <select class="field" name="assigned_to" data-ticket-assignee>
                            <option value="" disabled hidden @selected(blank($ticket->assigned_to))>Select assignee</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}" @selected($ticket->assigned_to === $agent->id)>{{ $agent->user_name }}</option>
                            @endforeach
                        </select>
                        <label class="label">Project <span class="text-rose-500">*</span></label>
                        <select class="field" name="team_id" data-ticket-project required>
                            <option value="" disabled hidden @selected(blank($ticket->team_id))>Select project</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" data-clients="{{ $project->members->where('user_role', 'client')->pluck('id')->implode(',') }}" data-agents="{{ $project->members->whereIn('user_role', ['agent', 'supervisor', 'admin'])->pluck('id')->implode(',') }}" data-devices="{{ $project->devices->pluck('id')->implode(',') }}" @selected($ticket->team_id === $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                        <label class="label">Affected Device <span class="text-rose-500">*</span></label>
                        <select class="field" name="device_id" data-ticket-device required>
                            <option value="" disabled hidden @selected(blank($ticket->device_id))>Select device</option>
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
                                <option value="{{ $client->id }}" @selected($ticket->requester_id === $client->id)>{{ $client->user_name }}</option>
                            @endforeach
                        </select>
                        <button class="btn-primary" type="button" data-open-dialog="close-confirm-dialog" id="save-workflow-btn" data-default-submit>Save Changes</button>
                    </form>

                    <dialog id="close-confirm-dialog" class="max-w-lg">
                        <div class="panel m-0">
                            <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                                <h3 class="text-xl font-black">Confirm Close Ticket</h3>
                                <button type="button" class="btn-soft" data-close-dialog>Cancel</button>
                            </div>
                            <p class="text-sm text-slate-600">You are changing the ticket status to <span class="font-semibold text-slate-900">Closed</span>. Closed tickets cannot be replied to or modified. Continue?</p>
                            <div class="mt-6 flex justify-end gap-2">
                                <button type="button" class="btn-soft" data-close-dialog>Cancel</button>
                                <button type="button" class="btn-primary" onclick="document.getElementById('update-workflow-form').submit()">Yes, Close Ticket</button>
                            </div>
                        </div>
                    </dialog>
                @endif
            </div>

            @unless($ticket->isClosed())
                <div class="panel">
                    <h3 class="text-xl font-black">Automation Tools</h3>
                    <form id="merge-form" method="POST" action="{{ route('tickets.merge', $ticket) }}" class="mt-4 space-y-3">
                        @csrf
                        <select class="field" name="target_ticket_id" id="merge-target-select">
                            <option value="" disabled hidden>Select target ticket to merge into</option>
                            @foreach($mergeTargets as $mergeTarget)
                                <option value="{{ $mergeTarget->id }}">{{ $mergeTarget->ticket_number }} — {{ $mergeTarget->subject }}</option>
                            @endforeach
                        </select>
                        <button class="btn-soft" type="button" data-open-dialog="merge-confirm-dialog">Merge Ticket</button>
                    </form>
                    <form id="split-form" method="POST" action="{{ route('tickets.split', $ticket) }}" class="mt-6 space-y-3">
                        @csrf
                        <input class="field" name="subject" id="split-subject" placeholder="New ticket subject">
                        <textarea class="field min-h-28" name="description" placeholder="Describe the scope of the split ticket"></textarea>
                        <button class="btn-soft" type="button" data-open-dialog="split-confirm-dialog">Split to New Ticket</button>
                    </form>
                </div>
            @endunless

            <dialog id="merge-confirm-dialog" class="max-w-lg">
                <div class="panel m-0">
                    <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                        <h3 class="text-xl font-black">Confirm Ticket Merge</h3>
                        <button type="button" class="btn-soft" data-close-dialog>Cancel</button>
                    </div>
                    <p class="text-sm text-slate-600">Ticket <span class="font-semibold text-slate-900">{{ $ticket->ticket_number }}</span> will be merged into the selected ticket. This action cannot be undone.</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn-soft" data-close-dialog>Cancel</button>
                        <button type="button" class="btn-primary" onclick="document.getElementById('merge-form').submit()">Yes, Merge</button>
                    </div>
                </div>
            </dialog>

            <dialog id="split-confirm-dialog" class="max-w-lg">
                <div class="panel m-0">
                    <div class="mb-4 flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                        <h3 class="text-xl font-black">Confirm Ticket Split</h3>
                        <button type="button" class="btn-soft" data-close-dialog>Cancel</button>
                    </div>
                    <p class="text-sm text-slate-600">A new ticket will be created from <span class="font-semibold text-slate-900">{{ $ticket->ticket_number }}</span>. The original ticket will remain open. Continue?</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="btn-soft" data-close-dialog>Cancel</button>
                        <button type="button" class="btn-primary" onclick="document.getElementById('split-form').submit()">Yes, Split</button>
                    </div>
                </div>
            </dialog>
        @endif


    </div>
</div>
@push('scripts')
<script>
    const saveBtn = document.getElementById('save-workflow-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            const status = document.getElementById('status-select')?.value;
            if (status === 'closed') {
                document.getElementById('close-confirm-dialog')?.showModal();
            } else {
                document.getElementById('update-workflow-form')?.submit();
            }
        });
    }
</script>
@endpush
@endsection
