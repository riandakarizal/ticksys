<div class="bg-white border border-slate-200 border-l-4 border-l-blue-500 rounded-xl overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
        <div class="flex items-center gap-3">
            <h3 class="text-sm font-bold text-slate-800">Equipment (EQ)</h3>
            <span class="text-xs text-slate-400">{{ $rows->count() }} project</span>
        </div>
        @if($isAdmin)
            <button type="button" class="text-xs text-blue-600 hover:text-blue-800 font-semibold" onclick="openAddModal('project'); document.getElementById('fp-type').value='EQ'; syncDocPanel();">+ Add</button>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="text-left text-slate-400 border-b border-slate-100 bg-slate-50/60">
                <tr>
                    <th class="px-4 py-2 font-medium w-8">#</th>
                    <th class="py-2 pr-3 font-medium">Job Name</th>
                    <th class="py-2 pr-3 font-medium">LOB</th>
                    <th class="py-2 pr-3 font-medium">Yr</th>
                    <th class="py-2 pr-3 font-medium">Client</th>
                    <th class="py-2 pr-3 font-medium">Area</th>
                    <th class="py-2 pr-3 font-medium">Partner / Value</th>
                    <th class="py-2 pr-3 font-medium">Work Value</th>
                    <th class="py-2 pr-3 font-medium">Documents</th>
                    <th class="py-2 pr-3 font-medium">Status</th>
                    <th class="py-2 pr-3 font-medium">Notes</th>
                    @if($isAdmin)<th class="py-2 pr-4"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $i => $p)
                    @php $ds = $p->docScore(); @endphp
                    <tr class="{{ $p->trashed() ? 'opacity-50 bg-slate-50' : 'hover:bg-slate-50/50' }}">
                        <td class="px-4 py-1.5 text-slate-400">{{ $i+1 }}</td>
                        <td class="py-1.5 pr-3 font-medium max-w-[220px] leading-relaxed">
                            {{ $p->nama }}
                            @if($p->trashed())<span class="ml-1 inline-flex items-center rounded px-1 py-0.5 text-[9px] font-medium bg-slate-200 text-slate-500">Archived</span>@endif
                        </td>
                        <td class="py-1.5 pr-3 whitespace-nowrap">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $p->lobBadgeClass() }}">{{ $p->lob }}</span>
                        </td>
                        <td class="py-1.5 pr-3 text-slate-500">{{ $p->tahun }}</td>
                        <td class="py-1.5 pr-3 text-slate-500 max-w-[120px] truncate" title="{{ $p->pemberi_kerja }}">{{ $p->pemberi_kerja ?: '-' }}</td>
                        <td class="py-1.5 pr-3 whitespace-nowrap text-slate-600">{{ $p->area ?: '-' }}</td>
                        <td class="py-1.5 pr-3 max-w-[150px]">
                            <div>{{ $p->mitra ?: '-' }}</div>
                            @if($p->nilai_mitra)<div class="text-slate-400 text-[10px]">{{ $rp($p->nilai_mitra) }}</div>@endif
                        </td>
                        <td class="py-1.5 pr-3 font-semibold whitespace-nowrap">{{ $rp($p->nilai_pekerjaan) }}</td>
                        <td class="py-1.5 pr-3">
                            @if($ds['score'] === -1)
                                <span class="text-slate-300 text-[10px]">N/A</span>
                            @else
                                <div class="flex gap-0.5 flex-wrap">
                                    @foreach($EQ_DOCS as $di => $dl)
                                        @php $dv = $p->docs[$di] ?? 0; @endphp
                                        <span class="inline-flex h-4 w-4 items-center justify-center rounded text-[7px] font-bold {{ $dv === 1 ? 'bg-green-500 text-white' : 'bg-slate-100 text-slate-400' }}" title="{{ $dl }}">{{ substr($dl,0,2) }}</span>
                                    @endforeach
                                </div>
                                @php $scoreClass = $ds['score'] >= 1 ? 'text-green-600' : ($ds['score'] == 0 ? 'text-red-500' : 'text-amber-500'); @endphp
                                <div class="text-[10px] font-semibold {{ $scoreClass }} mt-0.5">{{ $ds['ok'] }}/{{ $ds['total'] }}</div>
                            @endif
                        </td>
                        <td class="py-1.5 pr-3 whitespace-nowrap">
                            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $p->statusBadgeClass() }}">{{ $p->statusLabel() }}</span>
                        </td>
                        <td class="py-1.5 pr-3 text-slate-500 max-w-[150px] text-[10px]">{{ $p->keterangan ?: '' }}</td>
                        @if($isAdmin)
                            <td class="py-1.5 pr-4 whitespace-nowrap text-right">
                                @if($p->trashed())
                                    <form method="POST" action="{{ route('monitoring.restore', ['type'=>'projects','id'=>$p->id]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-purple-600 hover:text-purple-800 font-medium">Restore</button>
                                    </form>
                                @else
                                    <button type="button" onclick="openEditModal('project', {{ $p->id }}, {{ $p->toJson() }})" class="text-xs text-blue-600 hover:text-blue-800 font-medium mr-2">Edit</button>
                                    <form method="POST" action="{{ route('monitoring.destroy', ['type'=>'projects','id'=>$p->id]) }}" class="inline" onsubmit="return confirm('Archive this project?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-slate-400 hover:text-amber-700 font-medium">Archive</button>
                                    </form>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="12" class="px-4 py-8 text-center text-slate-400">No equipment data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
