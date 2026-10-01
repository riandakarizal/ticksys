# PRISM — Project Context untuk Claude Desktop

> Konteks project untuk Claude Desktop (Cowork / Project Knowledge). Bahasa kerja: Indonesia santai,
> istilah teknis tetap Inggris. Sumber kebenaran tetap kode di `C:\xampp\htdocs\prism` — kalau dokumen ini
> bertentangan dengan kode, percayai kode.

---

## 1. Tentang user

**Iqbal Muhamad** — PT IAS Support Indonesia (IASS), unit *Technology Operation & Maintenance*, airport
services. Peran hybrid: business analyst, system analyst, project coordinator, tech ops, sekaligus
implementer teknis. Bukan full-time software engineer, tapi nyaman diskusi teknis.

Skill: SQL, Python dasar, data analytics, business analysis, system design, Docker, WSL, VS Code, cloud
dasar, enterprise architecture. **Jangan jelaskan dasar-dasar ini** kecuali diminta.

Cara kerja yang disukai:
- Jawaban langsung & praktis, bukan saran generik.
- **MVP-first**: bikin yang jalan dulu → pahami kenapa jalan → rapikan & scale belakangan.
- Panduan step-by-step yang bisa langsung dieksekusi.
- Kecepatan eksekusi dan solusi realistis lebih penting daripada kesempurnaan.

PRISM juga dipakai sebagai portofolio sistem informasi untuk keperluan akademik (S2).

---

## 2. Apa itu PRISM

**PRISM — Project & Issue Management System.** Internal web app IASS yang menggantikan proses manual
(Excel per divisi, laporan masalah via WhatsApp/email, tanpa SLA, tanpa kontrol akses).

Dua fungsi utama:
1. **Helpdesk ticketing** — permintaan layanan & laporan masalah dengan SLA, kategori, custom field,
   merge/split, attachment, activity log, notifikasi in-app.
2. **Project monitoring** — data kontrak proyek, asset/equipment, manpower, dokumen kontrak, BOQ, untuk
   divisi Equipment & Technology.

Target bisnis: single source of truth data proyek, SLA enforcement (critical < 1 jam, high < 4 jam),
akses sesuai role, laporan status proyek < 5 menit.

Production: `https://prism.iassupport.id`.

---

## 3. Tech stack

| Layer | Pilihan |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Blade + Tailwind v4, vanilla JS (tanpa SPA framework), Vite |
| DB | MySQL/MariaDB (dev & prod), SQLite in-memory (test) |
| Excel | PhpSpreadsheet (import/export) |
| Test | Pest 4 + `RefreshDatabase` |
| Style | Laravel Pint |
| Local dev | XAMPP di Windows (`C:\xampp\htdocs\prism`) |
| Deploy | Docker image `iassprism/prism-app` + MySQL + Caddy (HTTPS) |

Git: remote bernama `ticksys`, branch kerja `boy`, main branch `main`.

---

## 4. Command penting

```bash
composer run dev        # serve + queue:listen + vite sekaligus (entrypoint dev utama)
php artisan serve       # app saja, http://localhost:8000
npm run build           # build asset produksi

composer test           # config:clear + php artisan test
php artisan test --filter=NamaTest
vendor/bin/pest tests/Feature/Monitoring/MonitoringAccessTest.php

vendor/bin/pint         # code style
```

Karena test jalan di SQLite, **hindari SQL khusus MySQL** (JSON functions, trik `LIKE` tertentu, dll.) di
code path yang dites.

---

## 5. Arsitektur yang wajib dipahami

### 5.1 RBAC — role string + scoping divisi (tanpa package permission)

`users.user_role` adalah kolom string biasa. Role aktif:

| Role | Keterangan |
|---|---|
| `superadmin` | Akses penuh, termasuk admin user & bulk import |
| `admin` | Kelola data monitoring (create/delete/restore, import/export equipment) |
| `siteadmin` | Bisa update data monitoring |
| `user` | Helpdesk + lihat monitoring sesuai divisi |
| `vip` | Lihat semua data (tanpa filter divisi), bisa lihat daftar user |
| `fin` | Finance — **daftar-putih**: hanya Dashboard, Project → Asset, Report → Data |

Dua lapis pengecekan:
- **Route-level**: middleware `EnsureRole` → `->middleware('role:superadmin,admin,...')` di
  `routes/web.php`. Karena `fin` sempit, route helpdesk/monitoring/manpower menyebut eksplisit
  `role:superadmin,admin,siteadmin,user,vip`. Menambah role sempit baru = sisir ulang semua daftar itu.
  Menyembunyikan menu di `layouts/app.blade.php` **tidak** menutup URL.
- **Data-level**: `User::allowedDivCodes()` → `null` (vip/superadmin, tanpa filter) atau array kode
  `pjct_div`, dari `UNIT_DIV_MAP` + divisi bawahan (hierarki `users.user_parid`). **Bukan global scope** —
  controller yang menyentuh data turunan `pjct_main` harus menerapkannya manual.

`UNIT_DIV_MAP`:

| Unit user | Div code yang boleh dilihat |
|---|---|
| Technology Operation & Maintenance | TC |
| Equipment Operation & Maintenance | EQ, EQREG1, EQREG2, EQREG3 |
| Technology Commercial | TC, TCREG1, TCREG2 |
| Equipment Commercial | EQC, EQ, EQREG1, EQREG2, EQREG3 |

> `docs/PRISM-DATABASE.md` dan `docs/PRISM-REDESIGN.md` masih menyebut role lama
> (`supervisor`/`agent`/`client`) — itu basi. Dokumentasi tabel/kolomnya tetap akurat.

### 5.2 Primary key string custom

Beberapa model membuat PK sendiri di `booted()` → `creating()`:
- `PjctMain`: `PJ0001`, `PJ0002`, … (ambil max lalu increment)
- `users.id`: `USR-NNN` manual
- `User` autentikasi pakai kolom `user_pass` (bukan `password`)

Insert di luar Eloquent (raw insert, seeder, import) **harus meniru format ini**. Bulk import memesan blok
ID di depan lewat `AstMain::nextIds()` / `PjctMain::nextIds()`.

### 5.3 Penyimpanan dokumen (`docfile/`)

Dokumen proyek (Kontrak, RKST, RAB, BAST, SOP) disimpan sebagai file di `docfile/PJxxxx/`, diindeks tabel
`pjct_doc`. Disajikan inline lewat `PjctDocController@show` dengan cek `allowedDivCodes()`.
`docfile/` **tidak** di-git dan **tidak** masuk dump DB — harus dicopy terpisah saat migrasi.

---

## 6. Domain & file kunci

| Area | File utama | Catatan |
|---|---|---|
| Tickets | `TicketController`, `TicketMessageController`, `Support/TicketManager.php`, `Support/Helpdesk.php` | SLA, kategori, custom field, merge/split, attachment, activity log |
| Project monitoring | `MonitoringController`, `PjctMain`, `PjctDoc`, `PjctBudgetController` | `pjct_div` TC/EQ/TCC/EQC · `pjct_status` UPC/OG/HVR/DLY/END · `pjct_type` RENT/SUPPLY/JASA |
| Asset | `AssetController`, `AstMain` | inventaris equipment, FK ke `pjct_main` via `ast_pjctid` |
| Manpower | `ManpowerController`, `PjctEmp` | staf per proyek |
| Bulk import asset | `AssetImportController`, `Support/AssetImportService.php` | superadmin only |
| Bulk import project | `ProjectImportController`, `Support/ProjectImportService.php` | superadmin only |
| Report → Data | `AssetReportController`, `Support/AssetReportExportService.php` | export `.xlsx` 2 sheet |
| Equipment import/export | `EqtImportController`, `Support/EqtImportService.php` | projects, handover, maintenance, vehicle |
| Admin | `Admin/UserController` | CRUD user (superadmin), lihat user (superadmin+vip) |
| Log | `SystemLog`, `ActivityLog`, `EqtChangeLog` | audit trail |

### Pola bulk import (asset & project)
`parse()` → `classify()` (tiap baris ditandai `new` / `skip` / `error`) → layar preview → `execute()`.
- File upload diparkir di disk `local`, session cuma simpan path, lalu di-parse ulang saat confirm
  (duplikat dicek ulang terhadap data live).
- **Insert-only**: duplikat di-skip, tidak pernah di-update.
- Mass insert melewati event Eloquent → PK dipesan di depan, `SystemLog` ditulis manual, dan
  `ProjectImportService::execute()` membuat folder `docfile/PJxxxx/` sendiri.

### Report → Data
Tarikan mentah seluruh kolom `ast_main` dengan filter gabungan (search, Type/Brand/Status/Kondisi/Region/
Lokasi/Tahun/Project, rentang `ast_delvdate`/`ast_purcdate`) + sorting whitelist `SORTABLE`. Export:
sheet `Data Asset` (kop filter, freeze pane, autofilter) + sheet `Ringkasan`. Baris dibaca via `cursor()`
→ **semua query agregat harus selesai sebelum iterasi** (MySQL unbuffered). ~3.500 baris ≈ 78 MB memori;
kalau data tumbuh jauh, ganti ke writer streaming.

---

## 7. Peta route (ringkas)

| Path | Role |
|---|---|
| `/login` | publik (throttle 5/menit) |
| `/dashboard` | semua user login |
| `/tickets/*` | semua kecuali `fin` |
| `/project/assets` | termasuk `fin` |
| `/project/manpower`, `/report/issues`, `/monitoring` | semua kecuali `fin` |
| `/project/assets/import/*`, `/monitoring/projects/import/*` | superadmin |
| `/report/data` (+ export) | superadmin, admin, siteadmin, vip, fin |
| `/vendor`, `/reports`, `/report/expenses` | superadmin, admin, siteadmin, vip |
| `PUT /monitoring/{type}/{id}` | superadmin, admin, siteadmin |
| create/delete/restore monitoring, import/export equipment, BOQ, upload docs | superadmin, admin |
| `/admin/users` | lihat: superadmin, vip · ubah: superadmin |

Semua route login juga melewati middleware `idle` (auto logout saat idle).

---

## 8. Deploy

**Lokal ke mesin lain:** `docker compose up --build -d` → app + MySQL, auto-import
`docker/db-init/01-prism.sql` saat volume kosong. Regenerate dump setelah perubahan data/skema besar:
`mysqldump -u root --default-character-set=utf8mb4 prism > docker/db-init/01-prism.sql`.
Detail: `docs/DOCKER-MIGRATION.md`.

**Production (`prism.iassupport.id`)** — `docker-compose.prod.yml` (Caddy + app + db, healthcheck `/up`):
1. Build & push (bisa dari laptop, ~30 menit):
   `docker buildx build --builder multiarch --platform linux/amd64,linux/arm64 -t iassprism/prism-app:latest --push .`
2. Verifikasi: `docker buildx imagetools inspect iassprism/prism-app:latest` (cek tanggal `created`).
3. Di server (user sendiri, SSH password):
   `docker compose -f docker-compose.prod.yml --env-file .env.production pull && docker compose -f docker-compose.prod.yml --env-file .env.production up -d`

Migration jalan otomatis di `docker/entrypoint.sh` (`php artisan migrate --force`). Detail:
`docs/CLOUD-MIGRATION.md`.

---

## 9. Dokumen lain di `docs/`

| File | Isi |
|---|---|
| `PRISM-SYSTEM-DOCUMENT.md` | Business background, problem statement, objectives, metrics |
| `PRISM-DATABASE.md` | Skema lengkap per kolom (role-nya basi) |
| `PRISM-REDESIGN.md` | Catatan redesign (role-nya basi) |
| `NIST-CSF2.md`, `RISK-REGISTER.md`, `SBOM.md` | Security & compliance |
| `DOCKER-MIGRATION.md`, `CLOUD-MIGRATION.md` | Panduan migrasi/deploy |

---

## 10. Riwayat fitur terbaru

1. Bulk asset import dari Excel
2. Bulk project import dari Excel
3. Report → Data + export Excel
4. Healthcheck container app
5. Role Finance (`fin`) — terbatas ke data asset & project

---

## 11. Aturan main saat bantu di project ini

- Percayai **kode** di atas dokumen (terutama soal role).
- Endpoint baru yang menyentuh data `pjct_main`/turunannya → terapkan `allowedDivCodes()` manual + role
  middleware yang tepat (ingat `fin`).
- Insert data di luar Eloquent → tiru format PK (`PJ0001`, `USR-001`, dst.).
- Jangan pakai SQL khusus MySQL di code path yang dites.
- Commit/push hanya kalau diminta; kerja di branch `boy`.
- Deploy: siapkan sampai image ter-push, serahkan command server ke user.
