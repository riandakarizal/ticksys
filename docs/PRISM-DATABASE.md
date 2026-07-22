# PRISM — Database Structure

**Database:** `prism` · **Engine:** MariaDB (XAMPP) · **Charset:** `utf8mb4_unicode_ci`

---

## Tables Overview

| Table | Rows | Keterangan |
|---|---|---|
| `users` | 10 | Akun pengguna PRISM |
| `pjct_main` | 66 | Data project Equipment & Technology |
| `ast_main` | 3,583 | Inventory aset per project |
| `pjct_emp` | 45 | Manpower / karyawan per project |
| `pjct_doc` | 65 | Dokumen legal/administratif per project (Kontrak, RKST, RAB, BAST, SOP) |
| `pjct_budget` | 0 | Budget project (belum diisi) |
| `pjct_expense` | 0 | Pengeluaran project (belum diisi) |
| `tickets` | — | Tiket helpdesk |
| `ticket_messages` | — | Pesan balasan tiket |
| `ticket_attachments` | — | Lampiran tiket |
| `categories` | 4 | Kategori tiket |
| `sla_policies` | 2 | Kebijakan SLA |
| `eqt_maintenances` | 12 | Log maintenance equipment |
| `eqt_handovers` | 6 | Log serah terima equipment |
| `eqt_vehicles` | 25 | Data kendaraan |
| `eqt_change_logs` | 0 | Log perubahan equipment |
| `teams` | 2 | Tim helpdesk |
| `activity_logs` | 0 | Log aktivitas sistem |
| `app_notifications` | 0 | Notifikasi in-app |

---

## Table Detail

### `users`
Akun login PRISM. PK manual format `USR-NNN`. Auth Laravel override via virtual `password` attribute.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | varchar(20) PK | Format: `USR-001` |
| `user_empid` | varchar(20) | NIK karyawan |
| `user_name` | varchar(20) | Nama tampil |
| `user_email` | varchar(225) | Login username |
| `user_pass` | varchar(225) | Bcrypt hash |
| `user_level` | varchar(20) | L1–L7 (jabatan struktural) |
| `user_role` | varchar(20) | `admin` / `supervisor` / `agent` / `client` / `vip` |
| `user_unit` | varchar(225) | Unit kerja |
| `user_div` | varchar(225) | Divisi / bidang |
| `user_parid` | varchar(225) | ID parent user (atasan langsung) |
| `user_status` | varchar(20) | `active` / `inactive` |

**Role & Level mapping:**

| Role | Akses |
|---|---|
| `admin` | Full akses |
| `supervisor` | Read + write monitoring, baca semua tiket |
| `agent` | Kelola tiket, baca monitoring |
| `vip` | Read-only semua data, tidak ada filter divisi |
| `client` | Tiket saja, tidak ada monitoring/project nav |

| Level | Jabatan |
|---|---|
| L1 | Direktur |
| L2 | Group Head |
| L3 | Division Head |
| L4 | Analyst |
| L5 | Senior Officer |
| L6 | Officer |
| L7 | Staff |

---

### `pjct_main`
Master data project. PK format `PJ0001`–`PJ0066`, auto-generate via Eloquent `creating` event.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | varchar(10) PK | Format: `PJ0001` |
| `pjct_contract` | varchar(255) | Nomor kontrak |
| `pjct_codate` | date | Tanggal kontrak |
| `pjct_div` | varchar(225) | Kode divisi: `TC`, `TCREG1`, `TCREG2`, `EQ`, `EQC`, `EQREG1`, `EQREG2`, `EQREG3` |
| `pjct_name` | varchar(255) | Nama project |
| `pjct_type` | varchar(255) | `RENT` / `SUPPLY` / `JASA` |
| `pjct_client` | varchar(255) | Nama klien |
| `pjct_area` | varchar(255) | Lokasi / area project |
| `pjct_value` | bigint(20) | Nilai kontrak (Rupiah) |
| `pjct_budgetid` | int(11) | FK → `pjct_budget.id` |
| `pjct_costart` | date | Tanggal mulai kontrak |
| `pjct_totalperiod` | int(11) | Durasi kontrak (bulan) |
| `pjct_coend_m` | date | Tanggal akhir kontrak |
| `pjct_status` | varchar(255) | `OG` / `HVR` / `DLY` / `END` |
| `pjct_misc` | text | Catatan tambahan |
| `deleted_at` | timestamp | Soft delete |

**Division scoping (`User::allowedDivCodes()`, dipakai oleh `MonitoringController` dan `PjctDocController`):**

| User unit | Bisa lihat pjct_div |
|---|---|
| Technology Operation & Maintenance | TC |
| Equipment Operation & Maintenance | EQ, EQREG1, EQREG2, EQREG3 |
| Technology Commercial | TC, TCREG1, TCREG2 |
| Equipment Commercial | EQC, EQ, EQREG1, EQREG2, EQREG3 |
| Parent user | Semua div dari user bawahannya |
| VIP | Semua (no filter) |

---

### `ast_main`
Inventory aset. 3,583 baris. FK ke `pjct_main` via `ast_pjctid`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | varchar(20) PK | Format: `AST-XXXXX` |
| `ast_type` | varchar(225) | Jenis aset (Laptop, GPS Tracker, dll) |
| `ast_brand` | varchar(225) | Merk |
| `ast_brandmodel` | varchar(225) | Model/seri produk |
| `ast_prodyear` | year(4) | Tahun produksi |
| `ast_serial` | varchar(225) | Serial number |
| `ast_vendid` | varchar(20) | ID vendor |
| `ast_username` | varchar(225) | Nama pengguna aset |
| `ast_userreg` | varchar(225) | Registrasi pengguna |
| `ast_userloc` | varchar(225) | Lokasi pengguna |
| `ast_userlocdet` | varchar(225) | Detail lokasi |
| `ast_cond` | varchar(225) | `Excellence` / `Good` / `Fair` / `Bad` |
| `ast_delvdate` | date | Tanggal pengiriman |
| `ast_purcdate` | date | Tanggal pembelian |
| `ast_stat` | varchar(225) | `Aktif` / `Aktif-Sewa` / `Aktif-SewaBeli` / `Back Up` / `Pinjam` / `Non-Aktif` |
| `ast_pjctid` | varchar(10) NULL | FK → `pjct_main.id` |
| `ast_docid` | varchar(20) | ID dokumen |
| `ast_misc` | text | Catatan |

**Distribusi status aset:**

| Status | Jumlah |
|---|---|
| Aktif | 3,358 |
| Aktif-Sewa | 137 |
| Aktif-SewaBeli | 42 |
| Back Up | 42 |
| Pinjam | 2 |
| Non-Aktif | 1 |
| UNKNOWN | 1 |

---

### `pjct_emp`
Data manpower / karyawan yang terlibat dalam project. 45 baris.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | varchar(20) PK | Format: `EM00001` |
| `emp_id` | varchar(20) | NIK karyawan |
| `emp_name` | varchar(225) | Nama lengkap |
| `emp_level` | varchar(20) | Level jabatan (L7, L7.1, dll) |
| `emp_levname` | varchar(20) | Nama jabatan (Teknisi, Admin, dll) |
| `emp_unit` | varchar(20) | Unit kerja |
| `emp_div` | varchar(20) | Site / lokasi penugasan |
| `emp_area` | varchar(225) | Region / area |
| `emp_pjctid` | varchar(20) | Nomor kontrak pekerjaan |
| `emp_coid` | varchar(225) | Nomor PKWT |
| `emp_contact` | varchar(225) NULL | Nomor HP |
| `emp_misc` | text NULL | Catatan |

---

### `pjct_doc`
Dokumen legal/administratif per project (Kontrak, RKST, RAB, BAST, SOP). 65 baris, diisi dari file fisik di
folder `docfile/PJxxxx/` (level teratas project saja, bukan subfolder). File yang nama-nya tidak mengandung
salah satu dari 5 keyword doc_type sengaja tidak diinsert.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | varchar(20) PK | Format: `DOC00001`, auto-generate via Eloquent `creating` event |
| `doc_number` | varchar(225) | Nomor dokumen (diekstrak dari prefix nama file sebelum " - ", atau nama file itu sendiri kalau tidak ada pemisah) |
| `doc_pjctid` | varchar(20) | FK → `pjct_main.id` |
| `doc_type` | varchar(225) | `KONTRAK` / `RKST` / `RAB` / `BAST` / `SOP` |
| `doc_filetype` | varchar(225) | Ekstensi file (`pdf`, `xlsx`, dll) |
| `doc_filename` | text | Nama file asli |
| `doc_filepath` | text | Path relatif ke disk `docfile` (mis. `PJ0001/nama-file.pdf`) |
| `doc_desc` | text | Deskripsi (bagian nama file setelah " - ", atau nama file itu sendiri) |

Diakses via route `monitoring.docs.show` (`PjctDocController@show`) — stream file langsung dari disk `docfile`
(`config/filesystems.php`) dengan `Content-Disposition: inline` supaya PDF terbuka di tab browser baru
(document viewer bawaan browser). Akses dibatasi oleh `User::allowedDivCodes()` — user tidak bisa buka dokumen
project di luar divisinya. Badge doc_type di kolom "Doc" halaman Monitoring adalah link ke route ini.

---

## Relasi Antar Tabel

```
pjct_main (id)
    └── ast_main (ast_pjctid)       — 1 project → banyak aset
    └── pjct_emp (emp_pjctid)       — via nomor kontrak (belum FK formal)
    └── pjct_doc (doc_pjctid)       — 1 project → banyak dokumen (Kontrak/RKST/RAB/BAST/SOP)
    └── pjct_budget (id)            — 1 project → 1 budget
    └── pjct_expense (pjct_id)      — 1 project → banyak pengeluaran

users (id)
    └── users (user_parid)          — self-referential, hierarki atasan-bawahan
```

---

## Catatan Teknis

- `pjct_main.id` dan `ast_main.ast_pjctid` harus kolasi **utf8mb4_unicode_ci** (ALTER TABLE sudah dilakukan)
- `users` tidak pakai `timestamps` — kolom `password` adalah virtual attribute yang map ke `user_pass`
- Auth Laravel: `getAuthPasswordName()` return `'password'`, `getAuthPassword()` return `user_pass`
- Import data: `ast_main` dari `ast_main.csv` (latin-1, delimiter `;`), `pjct_emp` dari `pjct_emp.csv` + fix NIK dari `Rekon TC & OM (1).xlsx` sheet Manpower
- `pjct_doc` diisi manual (one-off script, tidak ada migration) dari folder `docfile/PJxxxx/` yang ikut di-commit ke repo — lihat `docs/DOCKER-MIGRATION.md` soal implikasinya saat migrasi ke PC/server lain
