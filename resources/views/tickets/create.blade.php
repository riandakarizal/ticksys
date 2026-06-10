@extends('layouts.app', ['title' => 'Create Ticket', 'heading' => 'Create Ticket'])

@section('content')
<nav class="mb-4 flex items-center gap-1.5 text-sm" aria-label="Breadcrumb">
    <a href="{{ route('tickets.index') }}" class="text-slate-500 hover:text-slate-700">Tickets</a>
    <span class="text-slate-300">›</span>
    <span class="font-medium text-slate-900">Create Ticket</span>
</nav>

<div class="space-y-6">
    <div class="panel-soft">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_220px] lg:items-start">
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-blue-600">Ticket Management</p>
                <h2 class="mt-2 text-4xl font-black leading-tight text-slate-900">Report an Issue</h2>
                <p class="mt-3 max-w-3xl text-slate-600">Help us understand your issue by providing a clear subject and a complete description.</p>
            </div>
        </div>
    </div>

    <div class="panel">
        <h3 class="text-lg font-black text-slate-900">Tips for a Good Report</h3>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <p class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">Use a short, clear subject line.</p>
            <p class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">Describe the timeline, impact of the issue, and steps already taken.</p>
            <p class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">Attach screenshots or logs if available to speed up resolution.</p>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" data-ajax-form data-reload="false" class="mt-6">
    @csrf
    <div class="panel">
        <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start">
            <div class="space-y-6">
                <div>
                    <label class="label">Subject <span class="text-rose-500">*</span></label>
                    <input class="field" name="subject" value="{{ old('subject') }}" placeholder="e.g. Office VPN cannot connect" required>
                </div>
                <div>
                    <label class="label">Description <span class="text-rose-500">*</span></label>
                    <textarea class="field min-h-64" name="description" placeholder="Describe the issue, error messages, time of occurrence, and impact" required>{{ old('description') }}</textarea>
                </div>
                <div>
                    <label class="label">Attachments</label>
                    <input class="field file:mr-4 file:rounded-2xl file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:font-semibold file:text-white file:transition hover:file:bg-blue-700" type="file" name="attachments[]" multiple>
                    <p class="mt-2 text-xs text-slate-500">Supported formats: JPG, PNG, PDF, TXT, LOG, DOCX, XLSX, CSV. Max 5MB per file.</p>
                </div>
            </div>

            <div class="flex h-full flex-col gap-6 xl:border-l xl:border-slate-200 xl:pl-6">
                @if($customFields->count() || $devices->count())
                    <div>
                        <h3 class="text-lg font-black text-slate-900">Additional Details</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            @foreach($customFields as $field)
                                <div>
                                    <label class="label">{{ $field->name }} @if($field->is_required)<span class="text-rose-500">*</span>@endif</label>
                                    @if($field->type === 'textarea')
                                        <textarea class="field min-h-32" name="custom_fields[{{ $field->key }}]" placeholder="Enter {{ strtolower($field->name) }}">{{ old('custom_fields.'.$field->key) }}</textarea>
                                    @elseif($field->type === 'select')
                                        <select class="field" name="custom_fields[{{ $field->key }}]">
                                            <option value="" disabled hidden @selected(blank(old('custom_fields.'.$field->key)))>Select {{ $field->name }}</option>
                                            @foreach($field->options ?? [] as $option)
                                                <option value="{{ $option }}" @selected(old('custom_fields.'.$field->key) === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input class="field" type="{{ $field->type === 'number' ? 'number' : ($field->type === 'date' ? 'date' : 'text') }}" name="custom_fields[{{ $field->key }}]" value="{{ old('custom_fields.'.$field->key) }}" placeholder="Enter {{ strtolower($field->name) }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @php
                    $hasAdvancedValues = filled(old('sla_policy_id')) || filled(old('category_id')) || filled(old('subcategory_id')) || filled(old('assigned_to')) || filled(old('requester_id')) || filled(old('tags'));
                @endphp
                <div class="grid gap-4">
                    <div>
                        <label class="label">Project <span class="text-rose-500">*</span></label>
                        <select class="field" name="team_id" data-ticket-project required>
                            <option value="" disabled hidden @selected(blank(old('team_id')))>Select project</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" data-clients="{{ $project->members->where('role', 'client')->pluck('id')->implode(',') }}" data-agents="{{ $project->members->whereIn('role', ['agent', 'supervisor', 'admin'])->pluck('id')->implode(',') }}" data-devices="{{ $project->devices->pluck('id')->implode(',') }}" @selected((string) old('team_id') === (string) $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Affected Device <span class="text-rose-500">*</span></label>
                        <select class="field" name="device_id" data-ticket-device {{ blank(old('team_id')) ? 'disabled' : '' }} required>
                            <option value="" disabled hidden @selected(blank(old('device_id')))>Select a project first to see available devices</option>
                            @foreach($devices as $device)
                                <option value="{{ $device->id }}" data-project="{{ $device->team_id }}" @selected((string) old('device_id') === (string) $device->id)>{{ $device->name }}{{ $device->serial_number ? ' | '.$device->serial_number : '' }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500">Device list updates automatically based on the selected project.</p>
                    </div>
                    <div>
                        <label class="label">Priority <span class="text-rose-500">*</span></label>
                        <select class="field" name="priority" required>
                            <option value="" disabled hidden @selected(blank(old('priority')))>Select priority</option>
                            @foreach($priorities as $priority)
                                <option value="{{ $priority }}" @selected(old('priority') === $priority)>{{ \Illuminate\Support\Str::headline($priority) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <details class="rounded-2xl border border-slate-200" @if($hasAdvancedValues) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                            <span class="text-sm font-semibold text-slate-700">Advanced Options</span>
                            <span class="text-xs text-slate-400">SLA · Category · Assignment</span>
                        </summary>
                        <div class="grid gap-4 border-t border-slate-200 px-4 pb-4 pt-4">
                            <div>
                                <label class="label">SLA</label>
                                <select class="field" name="sla_policy_id">
                                    <option value="" disabled hidden @selected(blank(old('sla_policy_id')) && ! $slaPolicies->firstWhere('is_default', true))>Select SLA policy</option>
                                    @foreach($slaPolicies as $sla)
                                        <option value="{{ $sla->id }}" @selected((string) old('sla_policy_id') === (string) $sla->id || (blank(old('sla_policy_id')) && $sla->is_default))>{{ $sla->name }}{{ $sla->is_default ? ' (Default)' : '' }} | {{ $sla->response_minutes }}/{{ $sla->resolution_minutes }}m</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="label">Category</label>
                                    <select class="field" name="category_id" data-ticket-category>
                                        <option value="" disabled hidden @selected(blank(old('category_id')))>Select category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Subcategory</label>
                                    <select class="field" name="subcategory_id" data-ticket-subcategory>
                                        <option value="" disabled hidden @selected(blank(old('subcategory_id')))>Select subcategory</option>
                                        @foreach($categories as $category)
                                            @foreach($category->children as $child)
                                                <option value="{{ $child->id }}" data-parent="{{ $category->id }}" @selected((string) old('subcategory_id') === (string) $child->id)>{{ $category->name }} / {{ $child->name }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="label">Assign To</label>
                                <select class="field" name="assigned_to" data-ticket-assignee>
                                    <option value="" disabled hidden @selected(blank(old('assigned_to')))>Select assignee</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" @selected((string) old('assigned_to') === (string) $agent->id)>{{ $agent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if(!auth()->user()->isClient())
                                <div>
                                    <label class="label">Requester <span class="text-rose-500">*</span></label>
                                    <select class="field" name="requester_id" data-ticket-requester required>
                                        <option value="" disabled hidden @selected(blank(old('requester_id')))>Select client</option>
                                        @foreach($clients as $client)
                                            <option value="{{ $client->id }}" @selected((string) old('requester_id') === (string) $client->id)>{{ $client->name }} - {{ $client->email }}</option>
                                        @endforeach
                                    </select>
                                    @if($clients->isEmpty())
                                        <p class="mt-2 text-xs text-rose-500">No clients are assigned to your project yet.</p>
                                    @endif
                                </div>
                            @endif
                            <div>
                                <label class="label">Tags</label>
                                <input class="field" name="tags" value="{{ old('tags') }}" placeholder="e.g. network, urgent, payroll">
                            </div>
                        </div>
                    </details>
                </div>

                <div class="mt-6 flex justify-end">
                    <button class="btn-primary" type="submit">Submit Ticket</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
