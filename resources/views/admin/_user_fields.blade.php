{{-- Field form user, dipakai dialog Tambah & Edit. $u = user yang diedit (null saat tambah). --}}
@php
    $id     = $u?->id ?? 'new';
    $val    = fn (string $field) => $u?->{$field} ?? '';
    $roles  = [
        'user'       => 'User — teknisi lapangan',
        'siteadmin'  => 'Site Admin',
        'admin'      => 'Admin',
        'superadmin' => 'Super Admin',
        'vip'        => 'VIP — hanya-lihat manajemen',
        'fin'        => 'Finance',
        'qhsse'      => 'QHSSE — hanya-lihat',
    ];
    $levels = ['L1' => 'Direktur', 'L2' => 'Grouphead', 'L3' => 'Divisionhead', 'L4' => 'Analyst', 'L5' => 'Senior Officer', 'L6' => 'Officer', 'L7' => 'Project Employee'];
    $divs   = [
        '-' => '— Tidak ada (Direktorat) —',
        'Equipment & Technology Commercial' => 'Equipment & Technology Commercial',
        'Equipment & Technology Operation & Maintenance' => 'Equipment & Technology Operation & Maintenance',
        'Finance' => 'Finance',
    ];
    $units  = [
        '-' => '— Tidak ada —',
        'Technology Commercial' => 'Technology Commercial',
        'Equipment Commercial' => 'Equipment Commercial',
        'Technology Operation & Maintenance' => 'Technology Operation & Maintenance',
        'Equipment Operation & Maintenance' => 'Equipment Operation & Maintenance',
        'Asset' => 'Asset',
        'Quality, Health, Safety, Security & Environment' => 'Quality, Health, Safety, Security & Environment',
    ];
@endphp

<fieldset class="grid gap-x-4 gap-y-4 md:grid-cols-2">
    <legend class="mb-3 text-sm font-semibold text-slate-900 md:col-span-2">Identitas</legend>
    <label class="block">
        <span class="pt-label">Nama lengkap</span>
        <input class="pt-field" name="user_name" value="{{ $val('user_name') }}" required maxlength="225" autocomplete="off">
    </label>
    <label class="block">
        <span class="pt-label">NIK</span>
        <input class="pt-field" name="user_empid" value="{{ $val('user_empid') }}" required maxlength="20" inputmode="numeric" autocomplete="off">
    </label>
    <label class="block">
        <span class="pt-label">Email <span class="font-normal text-slate-500">(dipakai untuk login)</span></span>
        <input class="pt-field" type="email" name="user_email" value="{{ $val('user_email') }}" required autocomplete="off">
    </label>
    <label class="block">
        <span class="pt-label">{{ $u ? 'Password baru' : 'Password' }} @if($u)<span class="font-normal text-slate-500">(kosongkan bila tidak diganti)</span>@endif</span>
        <input class="pt-field" type="password" name="user_pass" @unless($u) required @endunless autocomplete="new-password">
    </label>
</fieldset>

<fieldset class="mt-6 grid gap-x-4 gap-y-4 md:grid-cols-2">
    <legend class="mb-3 text-sm font-semibold text-slate-900 md:col-span-2">Akses & organisasi</legend>
    <label class="block">
        <span class="pt-label">Role</span>
        <select class="pt-field" name="user_role" required>
            @unless($u)<option value="" disabled hidden selected>Pilih role</option>@endunless
            @foreach($roles as $value => $label)
                <option value="{{ $value }}" @selected($val('user_role') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="pt-label">Level</span>
        <select class="pt-field" name="user_level" required>
            @unless($u)<option value="" disabled hidden selected>Pilih level</option>@endunless
            @foreach($levels as $value => $label)
                <option value="{{ $value }}" @selected($val('user_level') === $value)>{{ $value }} — {{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="pt-label">Divisi</span>
        <select class="pt-field" name="user_div" required>
            @unless($u)<option value="" disabled hidden selected>Pilih divisi</option>@endunless
            @foreach($divs as $value => $label)
                <option value="{{ $value }}" @selected($val('user_div') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="pt-label">Unit</span>
        <select class="pt-field" name="user_unit" required>
            @unless($u)<option value="" disabled hidden selected>Pilih unit</option>@endunless
            @foreach($units as $value => $label)
                <option value="{{ $value }}" @selected($val('user_unit') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="pt-label">Atasan langsung <span class="font-normal text-slate-500">(approver ticket)</span></span>
        <select class="pt-field" name="user_parid">
            <option value="" @selected(in_array($val('user_parid'), ['', '-'], true))>— Tidak ada (top level) —</option>
            @foreach($users as $candidate)
                @continue($u && $candidate->id === $u->id)
                <option value="{{ $candidate->id }}" @selected($val('user_parid') === $candidate->id)>{{ $candidate->user_name }} · {{ $candidate->roleLabel() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="pt-label">Status</span>
        <select class="pt-field" name="user_status" required>
            <option value="active" @selected(($u?->user_status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected($u?->user_status === 'inactive')>Nonaktif</option>
        </select>
    </label>
</fieldset>
