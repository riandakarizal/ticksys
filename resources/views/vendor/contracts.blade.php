@extends('layouts.app', ['title' => 'Vendor Contracts', 'heading' => 'Vendor Contracts'])

@section('content')
<div class="flex flex-col items-center justify-center min-h-[50vh] gap-4 text-center">
    <div class="h-16 w-16 rounded-2xl bg-slate-100 flex items-center justify-center">
        <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
        </svg>
    </div>
    <div>
        <h2 class="text-lg font-black text-slate-800">Vendor Contracts</h2>
        <p class="text-sm text-slate-400 mt-1">Under development — coming soon</p>
    </div>
    <a href="{{ route('vendor.main') }}" class="btn-soft text-xs">← Back to Vendor</a>
</div>
@endsection
