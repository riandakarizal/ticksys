@extends('layouts.app', ['title' => 'Report: Expenses', 'heading' => 'Report — Expenses'])

@section('content')
<div class="flex flex-col items-center justify-center min-h-[50vh] gap-4 text-center">
    <div class="h-16 w-16 rounded-2xl bg-slate-100 flex items-center justify-center">
        <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>
        </svg>
    </div>
    <div>
        <h2 class="text-lg font-black text-slate-800">Report — Expenses</h2>
        <p class="text-sm text-slate-400 mt-1">Under development — coming soon</p>
    </div>
</div>
@endsection
