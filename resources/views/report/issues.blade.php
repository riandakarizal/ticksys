@extends('layouts.app', ['title' => 'Report: Issues', 'heading' => 'Report — Issues'])

@section('content')
<div class="flex flex-col items-center justify-center min-h-[50vh] gap-4 text-center">
    <div class="h-16 w-16 rounded-2xl bg-slate-100 flex items-center justify-center">
        <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
    </div>
    <div>
        <h2 class="text-lg font-black text-slate-800">Report — Issues</h2>
        <p class="text-sm text-slate-400 mt-1">Under development — coming soon</p>
    </div>
</div>
@endsection
