{{--
    Isian data surat yang sama di dialog Buat / Turunan / Edit.
    Parameter: $dialog (nama dialog, untuk mengisi ulang old() setelah validasi gagal), $projects, $defaultPic (opsional).
--}}
@php
    $isOld = old('_dialog') === $dialog;
    $val = fn (string $key, $default = '') => $isOld ? old($key, $default) : $default;
@endphp
<div>
    <label class="label">Perihal <span class="text-rose-500">*</span></label>
    <textarea class="field" name="subject" rows="2" maxlength="500" required data-field="subject"
        placeholder="mis. Permohonan Izin Masuk Teknisi">{{ $val('subject') }}</textarea>
</div>
<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label class="label">Tujuan Surat</label>
        <input class="field" name="recipient" list="letter-recipients" maxlength="255" value="{{ $val('recipient') }}"
            data-field="recipient" placeholder="mis. PT Angkasa Pura Indonesia" autocomplete="off">
    </div>
    <div>
        <label class="label">Penandatangan</label>
        <input class="field" name="signer" list="letter-signers" maxlength="255" value="{{ $val('signer') }}"
            data-field="signer" placeholder="mis. E & T OPS GH" autocomplete="off">
    </div>
    <div>
        <label class="label">PIC Internal</label>
        <input class="field" name="pic" list="letter-pics" maxlength="100" value="{{ $val('pic', $defaultPic ?? '') }}"
            data-field="pic" autocomplete="off">
    </div>
    <div>
        <label class="label">Project <span class="font-normal text-slate-400">(opsional)</span></label>
        <select class="field" name="pjct_id" data-field="pjct_id">
            <option value="">— Tidak terkait project —</option>
            @foreach($projects as $p)
                <option value="{{ $p->id }}" @selected($val('pjct_id') === $p->id)>{{ $p->id }} — {{ $p->pjct_name }}</option>
            @endforeach
        </select>
    </div>
</div>
<div>
    <label class="label">File surat <span class="font-normal text-slate-400">(opsional · PDF/JPG/PNG, maks. 5 MB)</span></label>
    <input class="field py-2" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" data-field="file">
    <p class="mt-1 hidden text-xs text-amber-600" data-file-multi-hint>Untuk beberapa nomor sekaligus, file diunggah lewat Edit di masing-masing nomor.</p>
</div>
