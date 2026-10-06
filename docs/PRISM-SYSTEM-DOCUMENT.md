# PRISM — System Documentation
**Project & Issue Management System**
Versi: 1.0 · Tanggal: 2026-07-05 · Author: Iqbal Muhamad
Organisasi: PT IAS Support Indonesia

---

## 1. Business Background

**PT IAS Support Indonesia (IASS)** adalah perusahaan *facility management* yang berperan sebagai mitra pendukung dengan pengalaman dalam pengelolaan area terpadu (*integrated area management*) di lingkungan bandara, kawasan industri, kawasan komersial, dan kawasan pariwisata. IASS hadir sebagai bagian dari perjalanan industri penerbangan Indonesia dengan semangat **#ServingWholeheartedly**.

Sebagai perusahaan yang mengelola berbagai area dan klien secara bersamaan, IASS menjalankan puluhan kontrak proyek aktif lintas divisi dan lokasi — mencakup penyewaan peralatan (*rental*), pengadaan barang (*supply*), dan layanan jasa (*jasa*). Di sisi operasional, tim internal juga menangani permintaan layanan dan laporan masalah dari klien maupun antar-divisi setiap harinya.

Sebelum PRISM, pengelolaan dilakukan secara manual:
- Data kontrak proyek tersebar di file Excel per divisi, tidak terstandarisasi
- Permintaan layanan dan laporan masalah dikirim via WhatsApp atau email tanpa struktur
- Tidak ada visibilitas real-time status proyek maupun SLA penanganan masalah
- Dokumen kontrak sulit ditelusuri dan rentan duplikasi data

PRISM dibangun sebagai **internal web application** berbasis Laravel untuk menggantikan proses manual tersebut — menyediakan sistem terpusat, berbasis peran, dan dapat diakses secara aman melalui jaringan internal IASS.

---

## 2. Problem Statement

| # | Masalah | Dampak |
|---|---------|--------|
| P-01 | Data proyek dan kontrak dikelola di Excel yang tidak terstandarisasi, sulit diagregasi dan dilacak perubahannya | Keputusan manajemen berdasarkan data yang tidak akurat atau tidak terkini |
| P-02 | Permintaan layanan dan laporan masalah dari klien maupun internal IASS masuk via WhatsApp/email tanpa kategorisasi dan prioritisasi | Masalah kritis tidak teridentifikasi, response time tidak terukur, tidak selaras dengan komitmen *#ServingWholeheartedly* |
| P-03 | Tidak ada SLA enforcement — tidak ada batas waktu respon dan penyelesaian yang disepakati dan dipantau | Kepuasan pengguna rendah, tidak ada akuntabilitas tim |
| P-04 | Tidak ada sistem peran — siapapun bisa mengakses dan mengubah data apapun | Risiko data korup, tidak ada jejak audit perubahan |
| P-05 | Laporan progres proyek dibuat manual, membutuhkan waktu konsolidasi yang lama | Manajemen tidak dapat memonitor portofolio proyek secara real-time |

---

## 3. Business Objectives & Success Metrics

### Objectives

| ID | Objective |
|----|-----------|
| O-01 | Menyediakan satu sumber kebenaran (single source of truth) untuk seluruh data proyek dan kontrak |
| O-02 | Menyediakan sistem tiket terstruktur dengan SLA enforcement untuk penanganan permintaan dan masalah |
| O-03 | Menerapkan kontrol akses berbasis peran agar setiap pengguna hanya dapat mengakses data sesuai kewenangannya |
| O-04 | Menyediakan dashboard monitoring proyek secara real-time untuk pengambilan keputusan manajemen |
| O-05 | Mendukung semangat *#ServingWholeheartedly* IASS dengan meningkatkan kecepatan dan akurasi penanganan permintaan layanan |
| O-06 | Menjadi fondasi portofolio sistem informasi untuk keperluan akademik (S2) |

### Success Metrics

| Metric | Target |
|--------|--------|
| Seluruh data proyek aktif termuat di sistem | 100% dari `pjct_main` |
| Response time tiket level critical | < 1 jam |
| Response time tiket level high | < 4 jam |
| Semua pengguna beroperasi sesuai role masing-masing | 0 unauthorized access incident |
| Waktu pembuatan laporan status proyek | < 5 menit (vs >1 jam manual) |

---

## 4. Stakeholder Analysis

| Stakeholder | Role di PRISM | Kebutuhan Utama | Hak Akses |
|-------------|--------------|-----------------|-----------|
| **System Administrator** | `admin` | Full control: kelola user, kategori, SLA policy, semua data | Semua fitur termasuk `/admin/*` |
| **Supervisor / Manajer** | `supervisor` | Monitor proyek & tiket, baca laporan, tidak perlu edit semua data | Dashboard, monitoring, laporan, tiket (baca + update) |
| **Agent / Staf Operasional** | `agent` | Handle tiket yang di-assign, update status, komunikasi dengan requester | Tiket yang relevan, tidak akses admin/monitoring |
| **Client / Pengguna Internal** | `client` | Submit permintaan/masalah, track status tiket milik sendiri | Hanya tiket yang dibuat sendiri |

---

## 5. Use Case / Actor-Goal Mapping

### Admin
| Use Case | Deskripsi |
|----------|-----------|
| UC-A01 | Kelola pengguna (tambah, edit, nonaktifkan, assign role & tim) |
| UC-A02 | Kelola kategori tiket (hierarki 2 level, warna) |
| UC-A03 | Kelola SLA Policy (response_minutes, resolution_minutes per kategori) |
| UC-A04 | Kelola tim/proyek (buat tim, assign lead, assign member) |
| UC-A05 | Kelola proyek kontrak (CRUD di monitoring) |
| UC-A06 | Restore data yang di-soft-delete |

### Supervisor
| Use Case | Deskripsi |
|----------|-----------|
| UC-S01 | Monitor seluruh tiket dan statusnya |
| UC-S02 | Monitor portofolio proyek (status, nilai kontrak, area, tipe) |
| UC-S03 | Assign atau reassign tiket ke agent |
| UC-S04 | Lihat laporan dan export data |
| UC-S05 | Eskalasi tiket (merge/split) |

### Agent
| Use Case | Deskripsi |
|----------|-----------|
| UC-AG01 | Lihat dan update tiket yang di-assign |
| UC-AG02 | Balas pesan tiket, kirim attachment |
| UC-AG03 | Update status tiket (in_progress → resolved) |
| UC-AG04 | Lihat riwayat tiket |

### Client
| Use Case | Deskripsi |
|----------|-----------|
| UC-C01 | Submit tiket baru (subject, deskripsi, kategori, attachment) |
| UC-C02 | Lihat status dan riwayat tiket milik sendiri |
| UC-C03 | Balas pesan di tiket milik sendiri |
| UC-C04 | Terima notifikasi in-app saat tiket diupdate |

---

## 6. Business Process (As-Is vs To-Be)

### Alur Penanganan Masalah / Permintaan

**As-Is (Manual)**
```
Pengguna temukan masalah
  → Kirim WhatsApp ke grup / PIC
    → PIC baca (jika online)
      → Tangani (tanpa batas waktu)
        → Update status via chat
          → Selesai (tidak terdokumentasi)
```
Masalah: tidak ada prioritas, tidak ada SLA, tidak terlacak, tidak ada audit trail.

**To-Be (PRISM)**
```
Pengguna submit tiket di PRISM (category, priority)
  → Sistem assign SLA otomatis berdasarkan kategori
    → Notifikasi ke agent/supervisor
      → Agent update status: open → in_progress → resolved
        → Notifikasi ke requester
          → Auto-close jika tidak ada respons setelah X hari
            → Tersimpan dengan full audit trail
```

---

### Alur Monitoring Proyek

**As-Is (Manual)**
```
PM buat kontrak baru
  → Isi Excel per divisi
    → Email ke supervisor setiap update
      → Supervisor konsolidasi manual dari banyak file
        → Laporan dibuat akhir bulan
```

**To-Be (PRISM)**
```
Admin/supervisor input proyek baru di PRISM Monitoring
  → Data langsung muncul di dashboard (KPI, charts)
    → Filter by status/type/area/year real-time
      → Nilai kontrak teragregasi otomatis
```

---

## 7. Functional Requirements

| FR-ID | Modul | Deskripsi | Role |
|-------|-------|-----------|------|
| FR-01 | Auth | Login dengan email dan password | Semua |
| FR-02 | Auth | Session auto-logout setelah 30 menit idle | Semua |
| FR-03 | Auth | Rate limiting login: maks 5 percobaan per menit | Semua |
| FR-04 | Auth | Logout manual dengan konfirmasi dialog | Semua |
| FR-05 | Dashboard | Tampilkan KPI proyek: total, OG, DLY, HVR, nilai kontrak | Admin, Supervisor |
| FR-06 | Dashboard | Tampilkan grafik distribusi status, tipe, dan tahun proyek | Admin, Supervisor |
| FR-07 | Dashboard | Tampilkan distribusi proyek per area | Admin, Supervisor |
| FR-08 | Dashboard | Tampilkan quick-stats tiket: open, in_progress, pending, resolved | Semua |
| FR-09 | Dashboard | Tampilkan tabel 8 proyek terbaru | Admin, Supervisor |
| FR-10 | Tiket | Buat tiket baru (subject, deskripsi, kategori, subkategori, priority, attachment) | Semua |
| FR-11 | Tiket | Lihat daftar tiket dengan filter status, priority, kategori, pencarian | Semua (sesuai scope role) |
| FR-12 | Tiket | Update status tiket (open/in_progress/pending/resolved/closed) | Agent, Supervisor, Admin |
| FR-13 | Tiket | Assign tiket ke agent atau tim | Supervisor, Admin |
| FR-14 | Tiket | Kirim dan terima pesan di dalam tiket | Semua |
| FR-15 | Tiket | Upload dan download attachment tiket | Semua |
| FR-16 | Tiket | Merge dua tiket menjadi satu | Supervisor, Admin |
| FR-17 | Tiket | Split satu tiket menjadi beberapa tiket | Supervisor, Admin |
| FR-18 | Tiket | SLA enforcement: tampilkan response_due_at dan resolution_due_at | Sistem otomatis |
| FR-19 | Tiket | Auto-close tiket yang sudah resolved terlalu lama tanpa aktivitas | Sistem otomatis |
| FR-20 | Monitoring | Tampilkan daftar proyek dengan filter tahun, status, tipe, pencarian | Supervisor, Admin |
| FR-21 | Monitoring | Tambah, edit, hapus (soft-delete) data proyek | Admin |
| FR-22 | Monitoring | Restore proyek yang di-archive | Admin |
| FR-23 | Monitoring | Tampilkan 4 KPI card: total, OG, DLY, total nilai kontrak | Supervisor, Admin |
| FR-24 | Notifikasi | Kirim notifikasi in-app saat tiket dibuat, diupdate, atau mendekati SLA deadline | Sistem otomatis |
| FR-25 | Notifikasi | Hapus notifikasi individual | Semua |
| FR-26 | Admin | CRUD pengguna dengan role dan assignment tim | Admin |
| FR-27 | Admin | CRUD kategori tiket (2 level: parent + child) | Admin |
| FR-28 | Admin | CRUD SLA Policy (response & resolution time per policy) | Admin |
| FR-29 | Admin | CRUD tim/proyek internal (buat tim, assign lead & member) | Admin |
| FR-30 | Laporan | Export data tiket ke CSV | Supervisor, Admin |
| FR-31 | Monitoring | Tampilkan badge dokumen (Kontrak/RKST/RAB/BAST/SOP) per proyek, klik untuk buka dokumen di tab baru | Semua (sesuai scope divisi) |

---

## 8. Non-Functional Requirements

| NFR-ID | Kategori | Deskripsi | Target |
|--------|----------|-----------|--------|
| NFR-01 | Performance | Waktu load halaman utama (dashboard) | < 2 detik pada jaringan LAN |
| NFR-02 | Performance | Query database dengan index yang tepat untuk tabel tiket dan proyek | Query time < 500ms |
| NFR-03 | Security | Seluruh password disimpan dalam bentuk hash | bcrypt (Laravel default) |
| NFR-04 | Security | CSRF token pada semua form | Laravel CSRF middleware aktif |
| NFR-05 | Security | Akses endpoint dibatasi per role | `EnsureRole` middleware |
| NFR-06 | Security | Session expire setelah 30 menit idle | `EnsureSessionIsActive` middleware |
| NFR-07 | Security | Rate limiting pada endpoint login | 5 request/menit |
| NFR-08 | Availability | Sistem tersedia selama jam operasional (07.00–22.00) | Uptime > 99% jam operasional |
| NFR-09 | Maintainability | Kode mengikuti Laravel conventions dan PSR-12 | Enforced via `laravel/pint` |
| NFR-10 | Scalability | Sistem dapat menangani hingga 50 pengguna concurrent | Arsitektur single-server dengan caching |
| NFR-11 | Usability | UI responsif untuk desktop dan tablet | Tailwind CSS responsive breakpoints |
| NFR-12 | Usability | Navigasi dapat diakses dalam maksimal 3 klik dari halaman manapun | Left sidebar dengan submenu |
| NFR-13 | Compatibility | Browser: Chrome, Edge, Firefox versi terbaru | Tested di Chrome 150+ |
| NFR-14 | Data Integrity | Soft delete pada data proyek (tidak dihapus permanen) | `SoftDeletes` trait Laravel |
| NFR-15 | Audit | Setiap perubahan tiket tercatat di activity log | `ActivityLog` model |

---

## 9. System Architecture

### Stack Teknologi

| Layer | Teknologi | Versi |
|-------|-----------|-------|
| Backend Framework | Laravel | 13.2.0 |
| Runtime | PHP | 8.5.7 |
| Database | MySQL (XAMPP) | 8.x |
| Frontend CSS | Tailwind CSS | 4.2.2 |
| Build Tool | Vite | 8.0.0 |
| Charting | Chart.js | 4.5.1 |
| Datatable | simple-datatables | 10.2.0 |
| HTTP Client | Axios | 1.13.6 |

### Pola Arsitektur

PRISM menggunakan pola **MVC (Model-View-Controller)** bawaan Laravel:

```
Browser
  ↓ HTTP Request
routes/web.php          ← URL routing + middleware stack
  ↓
Middleware              ← auth, idle, role, CSRF
  ↓
Controller              ← business logic, query Eloquent
  ↓
Model (Eloquent)        ← ORM ke MySQL
  ↓
View (Blade)            ← HTML rendering, @yield, @section
  ↓ HTTP Response
Browser
```

### Struktur Direktori Kunci

```
prism/
├── app/
│   ├── Http/
│   │   ├── Controllers/       ← DashboardController, TicketController, MonitoringController, ...
│   │   └── Middleware/        ← EnsureRole, EnsureSessionIsActive
│   ├── Models/                ← User, Ticket, PjctMain, Category, SlaPolicy, Team, ...
│   └── Support/               ← Helpdesk (ticket visibility logic)
├── resources/
│   ├── views/
│   │   ├── layouts/app.blade.php   ← Master layout: sidebar + topbar
│   │   ├── dashboard.blade.php
│   │   ├── tickets/
│   │   ├── monitoring/
│   │   ├── project/           ← assets, manpower (stub)
│   │   ├── vendor/            ← main, contracts (stub)
│   │   └── report/            ← issues, expenses (stub)
│   └── css/app.css            ← Tailwind + custom theme
├── routes/web.php             ← Semua route definisi
└── docs/                      ← Dokumentasi sistem
```

---

## 10. Deployment Architecture

### Current State (Development)
```
[Developer Laptop]
  └── XAMPP
        ├── Apache  → port 80
        ├── MySQL   → port 3306 (DB: prism)
        └── PHP 8.5.7 → Laravel artisan serve → port 8080
```

### Target State (Production)

```
[Client Device]
  ↓ WireGuard VPN tunnel (encrypted)
[VPN Server / Gateway]
  ↓ Internal network
[Application Server]
  ├── Nginx (reverse proxy, TLS termination)
  │     └── Laravel PHP-FPM → port 9000
  ├── MySQL 8.x (lokal, tidak exposed ke public)
  └── Storage (file attachments tiket)
```

**Catatan keamanan deployment:**
- HTTPS wajib aktif sebelum expose ke non-localhost
- `APP_DEBUG=false`, `APP_ENV=production` di `.env` produksi
- `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`
- MySQL tidak boleh diakses dari luar server (bind: 127.0.0.1)

---

## 11. Data Architecture

### Tabel Utama dan Kolom Kunci

#### `users`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint PK | |
| company_id | bigint FK | Multi-tenant support |
| name | varchar | Nama lengkap |
| email | varchar unique | Login identifier |
| password | varchar | bcrypt hash |
| role | enum | admin, supervisor, agent, client |
| job_title | varchar | Jabatan |
| phone | varchar | |
| is_active | boolean | Soft-disable user |

#### `tickets`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint PK | |
| ticket_number | varchar unique | Auto-generated |
| company_id | FK | |
| requester_id | FK → users | Pemohon |
| created_by | FK → users | Pembuat tiket |
| assigned_to | FK → users | Agent yang handle |
| team_id | FK → teams | |
| category_id | FK → categories | |
| sla_policy_id | FK → sla_policies | |
| subject | varchar | |
| description | text | |
| status | enum | open, in_progress, pending, resolved, closed |
| priority | enum | low, medium, high, critical |
| response_due_at | datetime | Batas waktu respon (SLA) |
| resolution_due_at | datetime | Batas waktu penyelesaian (SLA) |
| resolved_at | datetime | Waktu actual resolved |
| merged_into_ticket_id | FK → tickets | Jika di-merge |

#### `pjct_main` (Project Monitoring)
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint PK | |
| pjct_contract | varchar | Nomor kontrak |
| pjct_codate | date | Tanggal kontrak |
| pjct_div | varchar | Divisi |
| pjct_name | varchar | Nama proyek |
| pjct_type | enum | RENT, SUPPLY, JASA |
| pjct_client | varchar | Nama klien |
| pjct_area | varchar | Area/lokasi |
| pjct_value | bigint | Nilai kontrak (Rupiah) |
| pjct_costart | date | Tanggal mulai |
| pjct_totalperiod | int | Durasi (bulan) |
| pjct_coend_m | date | Tanggal berakhir |
| pjct_status | enum | OG, HVR, DLY, END |
| pjct_misc | text | Catatan |
| deleted_at | datetime | Soft delete |

#### `categories`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint PK | |
| company_id | FK | |
| parent_id | FK → categories | NULL = parent category |
| name | varchar | |
| color | varchar | Hex color untuk UI |

#### `sla_policies`
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| id | bigint PK | |
| company_id | FK | |
| name | varchar | Nama policy |
| response_minutes | int | Batas waktu respon |
| resolution_minutes | int | Batas waktu selesai |
| is_default | boolean | Policy default |

### Relasi Antar Entitas

```
companies ─── users (company_id)
           ├── tickets (company_id)
           ├── categories (company_id)
           ├── sla_policies (company_id)
           └── teams (company_id)

users ──┬── tickets.requester_id
        ├── tickets.created_by
        ├── tickets.assigned_to
        ├── team_user (pivot: many-to-many → teams)
        ├── activity_logs
        └── app_notifications

tickets ──┬── ticket_messages
          ├── ticket_attachments
          ├── activity_logs
          ├── categories
          └── sla_policies

pjct_main   (standalone — tidak FK ke tickets saat ini)
```

---

## 12. Security Architecture

Lihat: [`docs/NIST-CSF2.md`](NIST-CSF2.md) untuk assessment lengkap NIST CSF 2.0.
Lihat: [`docs/SBOM.md`](SBOM.md) untuk daftar dependency dan lisensi.
Lihat: [`docs/RISK-REGISTER.md`](RISK-REGISTER.md) untuk daftar risiko dan mitigasi.

**Ringkasan kontrol yang aktif:**

| Kontrol | Implementasi |
|---------|-------------|
| Autentikasi | Session-based Laravel Auth, bcrypt password |
| Otorisasi | RBAC via `EnsureRole` middleware (4 role) |
| CSRF Protection | Laravel CSRF token middleware (semua form) |
| Session Management | Idle timeout 30 menit, session invalidate saat logout |
| Rate Limiting | Login endpoint: 5 request/menit |
| SQL Injection | Eloquent ORM + parameterized queries |
| Audit Trail | `ActivityLog` model per tiket event |
| Soft Delete | `SoftDeletes` trait pada `PjctMain` |

---

## 13. UI/UX Prototype

PRISM menggunakan **functional prototype** — aplikasi sudah berjalan di `localhost:8080`.

### Design Language
| Elemen | Nilai |
|--------|-------|
| Brand color | Teal `#1ab5b5` (Tailwind: `brand-600`) |
| Background | `bg-slate-100` |
| Panel/Sidebar | `bg-white` |
| Font | Manrope (Google Fonts) |
| Border radius | `rounded-2xl` / `rounded-3xl` (konsisten) |
| Layout | Left sidebar collapsible (w-60 expanded / w-16 collapsed) |

### Navigasi
```
Sidebar (kiri, collapsible)
├── Dashboard
├── Project
│   ├── Main (monitoring.index)
│   ├── Asset [stub]
│   └── Manpower [stub]
├── Vendor
│   ├── Main [stub]
│   └── Contract [stub]
├── Report
│   ├── Issues [stub]
│   └── Expenses [stub]
├── Tickets
└── Admin [admin only]
```

### Halaman yang Sudah Jadi
| Halaman | Route | Status |
|---------|-------|--------|
| Dashboard | `/dashboard` | ✅ Fungsional |
| Ticket List | `/tickets` | ✅ Fungsional |
| Ticket Detail | `/tickets/{id}` | ✅ Fungsional |
| Project Monitoring | `/monitoring` | ✅ Fungsional |
| Admin — Users | `/admin/users` | ✅ Fungsional |
| Admin — Categories | `/admin/categories` | ✅ Fungsional |
| Admin — SLA | `/admin/sla-policies` | ✅ Fungsional |
| Project — Asset | `/project/assets` | 🔲 Stub |
| Project — Manpower | `/project/manpower` | 🔲 Stub |
| Vendor — Main | `/vendor` | 🔲 Stub |
| Vendor — Contract | `/vendor/contracts` | 🔲 Stub |
| Report — Issues | `/report/issues` | 🔲 Stub |
| Report — Expenses | `/report/expenses` | 🔲 Stub |
| Report — Data | `/report/data` | ✅ Fungsional |

---

## 14. Testing Strategy

### Jenis Pengujian

| Tipe | Tool | Scope | Status |
|------|------|-------|--------|
| Unit Test | PestPHP | Model logic, helper methods | ❌ Belum |
| Feature Test | PestPHP (Laravel) | Controller endpoint, form submission | ❌ Belum |
| Browser/UAT | Manual | Alur end-to-end per role | ⚠️ Ad-hoc |

### Skenario UAT Minimal

| TC-ID | Skenario | Role | Expected Result |
|-------|----------|------|----------------|
| TC-01 | Login dengan kredensial valid | Semua | Redirect ke dashboard |
| TC-02 | Login dengan password salah 6x | Semua | Blocked 1 menit (throttle) |
| TC-03 | Idle 31 menit, lalu akses halaman | Semua | Redirect ke login, pesan session expired |
| TC-04 | Client coba akses `/monitoring` | client | 403 Forbidden |
| TC-05 | Agent coba akses `/admin/users` | agent | 403 Forbidden |
| TC-06 | Admin buat tiket baru | admin | Tiket muncul di list, notifikasi terbuat |
| TC-07 | Supervisor filter proyek by status OG | supervisor | Hanya proyek OG tampil |
| TC-08 | Admin tambah proyek baru via modal | admin | Proyek muncul di tabel, KPI diupdate |
| TC-09 | Soft-delete proyek | admin | Proyek hilang dari list, bisa di-restore |
| TC-10 | Merge dua tiket | supervisor | Tiket slave ditutup, pesan digabung |
| TC-11 | SLA breach — tiket melewati resolution_due_at | Sistem | Badge SLA breached tampil |
| TC-12 | Logout via sidebar | Semua | Session dihapus, redirect ke login |

---

## 15. Development Roadmap

### Phase 0 — Foundation ✅ DONE
- Setup Laravel project, autentikasi, RBAC
- Database schema: users, tickets, categories, SLA policies, teams
- Ticket CRUD lengkap: create, assign, update status, messages, attachments, merge, split
- Admin panel: user management, category, SLA, team management

### Phase 1 — Project Monitoring ✅ DONE
- Import data proyek dari CSV (`pjct_main` — 66 records)
- Model `PjctMain` dengan status/type badge methods
- `MonitoringController` dengan filter multi-parameter
- Halaman monitoring dengan tabel, KPI, modal CRUD
- Tabel `pjct_doc` (Kontrak/RKST/RAB/BAST/SOP) + kolom "Doc" di tabel monitoring, badge-nya klik untuk buka
  dokumen asli (`docfile/PJxxxx/`) di tab baru via `PjctDocController`

### Phase 2 — UI Redesign ✅ DONE
- Migrasi layout dari top navbar ke left collapsible sidebar
- New navigation: Project / Vendor / Report / Tickets / Admin
- Dashboard baru: project-centric analytics (donut chart, bar charts, area breakdown)
- Stub pages untuk modul yang akan datang

### Phase 3 — Modul Lanjutan 🔲 PLANNED
- **Asset Management**: integrasi `ast_main` + `ast_spec` (join by brand+model)
- **Manpower**: data SDM per proyek
- **Vendor**: master vendor, riwayat kontrak vendor
- **Report**: laporan issues & expenses terstruktur

### Phase 4 — Hardening & Deploy 🔲 PLANNED
- HTTPS via Nginx + TLS
- WireGuard VPN setup
- Email notification via SMTP
- Backup otomatis terjadwal
- MFA untuk admin dan supervisor
- Data retention policy implementation

---

## 16. Risk Analysis

Lihat: [`docs/RISK-REGISTER.md`](RISK-REGISTER.md) — 20 risiko terdokumentasi berdasarkan NIST CSF 2.0, dengan scoring Likelihood × Impact, risk level (Low/Medium/High/Critical), dan rekomendasi mitigasi per risiko.

**Top 3 Risiko Tertinggi (Risk Score ≥ 12):**

| Risk ID | Deskripsi | Score | Level |
|---------|-----------|-------|-------|
| R-01 | Tidak ada HTTPS — data transit tidak terenkripsi | 15 | High |
| R-02 | Tidak ada MFA — single-factor authentication | 15 | High |
| R-04 | APP_DEBUG terbuka di produksi — stack trace expose | 12 | High |

---

## 17. Future Enhancement

| # | Enhancement | Modul | Estimasi Effort |
|---|-------------|-------|----------------|
| FE-01 | Asset Management — integrasi `ast_main` + `ast_spec` dengan join brand+model | Project → Asset | Medium |
| FE-02 | Manpower tracking per proyek (SDM, jam kerja, posisi) | Project → Manpower | Medium |
| FE-03 | Master Vendor + riwayat kontrak vendor | Vendor | Medium |
| FE-04 | Laporan Issues terstruktur (tren, kategori, resolusi) | Report → Issues | Medium |
| FE-05 | Laporan Expenses per proyek dengan breakdown | Report → Expenses | Medium |
| FE-06 | Email notification untuk SLA warning dan tiket update | System-wide | Low |
| FE-07 | MFA (TOTP) untuk role admin dan supervisor | Auth | Low |
| FE-08 | Export laporan proyek ke Excel (via PhpSpreadsheet) | Monitoring | Low |
| FE-09 | Kalender / timeline view proyek (Gantt sederhana) | Dashboard | High |
| FE-10 | Mobile-responsive view yang optimal untuk smartphone | UI | Medium |
| FE-11 | AISS Integration — AI chatbot untuk query data PRISM via RAG | AISS (out of scope saat ini) | High |
| FE-12 | Audit log viewer di admin panel | Admin | Low |
| FE-13 | Dark mode toggle | UI | Low |
