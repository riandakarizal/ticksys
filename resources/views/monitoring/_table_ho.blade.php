<div class="bg-white border border-slate-200 border-l-4 border-l-emerald-500 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
        <div class="flex items-center gap-3">
            <h3 class="text-sm font-bold text-slate-800">Hand Over Pipeline</h3>
            <span class="text-xs text-slate-400">{{ $rows->count() }} item</span>
        </div>
        @if($isAdmin)
            <button type="button" class="text-xs text-blue-600 hover:text-blue-800 font-semibold" onclick="openAddModal('handover')">+ Add</button>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100 bg-slate-50/60">
                <tr>
                    <th class="px-4 py-2 font-medium w-8">#</th>
                    <th class="py-2 pr-3 font-medium">Job Name</th>
                    <th class="py-2 pr-3 font-medium">LOB</th>
                    <th class="py-2 pr-3 font-medium">Client</th>
                    <th class="py-2 pr-3 font-medium">Area</th>
                    <th class="py-2 pr-3 font-medium">Partner</th>
                    <th class="py-2 pr-3 font-medium">Status</th>
                    <th class="py-2 pr-3 font-medium">Notes</th>
                    @if($isAdmin)<th class="py-2 pr-4"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $i => $h)
                    <tr class="{{ $h->trashed() ? 'opacity-50 bg-slate-50' : 'hover:bg-slate-50/50' }}">
                        <td class="px-4 py-1.5 text-slate-400">{{ $i+1 }}</td>
                        <td class="py-1.5 pr-3 font-medium max-w-[250px] leading-relaxed">
                            {{ $h->nama }}
                            @if($h->trashed())<span class="ml-1 inline-flex items-center rounded px-1 py-0.5 text-[9px] font-medium bg-slate-200 text-slate-500">Archived</span>@endif
                        </td>
                        <td class="py-1.5 pr-3 whitespace-nowrap">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $h->lobBadgeClass() }}">{{ $h->lob ?: '-' }}</span>
                        </td>
                        <td class="py-1.5 pr-3 text-slate-500">{{ $h->pemberi_kerja ?: '-' }}</td>
                        <td class="py-1.5 pr-3 whitespace-nowrap text-slate-600">{{ $h->area ?: '-' }}</td>
                        <td class="py-1.5 pr-3 text-slate-600">{{ $h->mitra ?: '-' }}</td>
                        <td class="py-1.5 pr-3 whitespace-nowrap">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $h->statusBadgeClass() }}">{{ $h->status }}</span>
                        </td>
                        <td class="py-1.5 pr-3 text-slate-500 max-w-[180px] text-[10px]">{{ $h->keterangan ?: '' }}</td>
                        @if($isAdmin)
                            <td class="py-1.5 pr-4 whitespace-nowrap text-right">
                                @if($h->trashed())
                                    <form method="POST" action="{{ route('monitoring.restore', ['type'=>'handovers','id'=>$h->id]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-purple-600 hover:text-purple-800 font-medium">Restore</button>
                                    </form>
                                @else
                                    <button type="button" onclick="openEditModal('handover', {{ $h->id }}, {{ $h->toJson() }})" class="text-xs text-blue-600 hover:text-blue-800 font-medium mr-2">Edit</button>
                                    <form method="POST" action="{{ route('monitoring.destroy', ['type'=>'handovers','id'=>$h->id]) }}" class="inline" onsubmit="return confirm('Archive this item?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-slate-400 hover:text-amber-700 font-medium">Archive</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">No hand over data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
