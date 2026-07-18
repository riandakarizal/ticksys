@extends('layouts.app', ['title' => 'Vendor', 'heading' => 'Vendor'])

@section('content')
<div class="flex flex-col items-center justify-center min-h-[50vh] gap-4 text-center">
    <div class="h-16 w-16 rounded-2xl bg-slate-100 flex items-center justify-center">
        <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
        </svg>
    </div>
    <div>
        <h2 class="text-lg font-black text-slate-800">Vendor</h2>
        <p class="text-sm text-slate-400 mt-1">Under development — coming soon</p>
    </div>
</div>
@endsection
