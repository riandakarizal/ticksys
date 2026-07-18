# PRISM UI Redesign — Change Log

## Overview
Full redesign from top horizontal navbar to **FlowMail-style left collapsible sidebar** layout.

---

## 1. Layout (`resources/views/layouts/app.blade.php`)

**Before:** Top horizontal navbar, `mx-auto max-w-7xl` centered content, right sidebar notifications.

**After:** Left fixed sidebar (collapsible), full-width main content area.

### Sidebar
- Width: `w-60` (15rem) expanded / `w-16` (4rem) collapsed
- White background (`bg-white`), right border + shadow
- Toggle button in header row — persists state in `localStorage` key `prism-sb-collapsed`
- `.sidebar-label` elements (`display:none` when `.collapsed`) hide text labels and chevrons
- Submenu accordions for Project, Vendor, Report groups (JS toggle, chevron rotates 90deg when open)

### Navigation Structure (new)
| Menu | Route |
|------|-------|
| Dashboard | `dashboard` |
| Project → Main | `monitoring.index` |
| Project → Asset | `project.assets` |
| Project → Manpower | `project.manpower` |
| Vendor → Main | `vendor.main` |
| Vendor → Contract | `vendor.contracts` |
| Report → Issues | `report.issues` |
| Report → Expenses | `report.expenses` |
| Tickets | `tickets.index` |
| Admin *(admin only)* | `admin.users.index` |

### Topbar
- Sticky `h-16` white bar per page
- Shows `$heading` variable (passed from each view via `@extends('layouts.app', ['heading' => '...'])`)
- Notification bell with dropdown (uses `notificationsFeed()` relation)
- Page heading + user role pill

### Brand Colors
- Primary: `brand-600` = `#17a2a2` (teal)
- Active nav item: `bg-[#f0fdfd]` / `text-[#0d9191]`
- Body background: `bg-slate-100`
- Sidebar/header background: `bg-white`

---

## 2. New Routes (`routes/web.php`)
Added under `role:supervisor,admin` middleware group:

```php
Route::get('/project/assets',    ...)->name('project.assets');
Route::get('/project/manpower',  ...)->name('project.manpower');
Route::get('/vendor',            ...)->name('vendor.main');
Route::get('/vendor/contracts',  ...)->name('vendor.contracts');
Route::get('/report/issues',     ...)->name('report.issues');
Route::get('/report/expenses',   ...)->name('report.expenses');
```

---

## 3. Stub Views Created
All show "Under Development — Coming Soon" placeholder with icon:

| View File | Route Name |
|-----------|------------|
| `resources/views/project/assets.blade.php` | `project.assets` |
| `resources/views/project/manpower.blade.php` | `project.manpower` |
| `resources/views/vendor/main.blade.php` | `vendor.main` |
| `resources/views/vendor/contracts.blade.php` | `vendor.contracts` |
| `resources/views/report/issues.blade.php` | `report.issues` |
| `resources/views/report/expenses.blade.php` | `report.expenses` |

---

## 4. Dashboard Redesign (`resources/views/dashboard.blade.php`)

**Before:** Ticket-centric — status chart, recent tickets, agent performance, monthly ticket volume.

**After:** Project-centric with ticket quick-stats section.

### KPI Cards (top row)
- Total Projects
- On Going (green)
- Delay (amber)
- Hand Over (blue)
- Total Contract Value (Rp formatted in billions)

### Charts (second row)
- **Status Donut** — pure SVG, segments: OG/HVR/DLY/END
- **By Type** — horizontal bar (RENT violet / SUPPLY sky / JASA teal)
- **Projects by Year** — vertical bar chart from `pjct_codate` year grouping

### Bottom Row
- **Recent Projects table** — last 8 by `pjct_codate`, columns: Name/Contract, Client, Type badge, Value (Rp M), Status badge
- **By Area** — horizontal mini-bars, top 8 areas
- **Ticket Quick-stats** — 2×2 grid: Open/In Progress/Pending/Resolved counts

---

## 5. DashboardController (`app/Http/Controllers/DashboardController.php`)

**Before:** Ticket analytics — statusCounts, monthlySeries, categorySeries, agentPerformance, SLA compliance.

**After:** Project analytics primary, ticket counts secondary.

```php
$allProjects = PjctMain::all();
$projectKpi  = [total, og, hvr, dly, end, nilai]
$byType      = [RENT, SUPPLY, JASA counts]
$byArea      = top 8 areas by project count
$byYear      = projects grouped by pjct_codate year
$recentProjects = latest 8 by pjct_codate

$ticketCounts = [open, in_progress, pending, resolved]
```

---

## Database Notes
- Main project table: `pjct_main` (replaced old `eqt_projects`)
- Status codes: `OG` On Going · `HVR` Hand Over · `DLY` Delay · `END` Ended
- Type codes: `RENT` · `SUPPLY` · `JASA`
- `pjct_value` column type: `bigint` (modified from INT due to values > 2.1B)

---

## 6. Restrukturisasi Tabel `users`

Tabel `users` dirancang ulang sepenuhnya tanpa `timestamps` dan tanpa `remember_token`.

### Schema Baru
```sql
CREATE TABLE users (
  id          varchar(20)  PRIMARY KEY,  -- manual, e.g. USR-001
  user_empid  varchar(20),
  user_name   varchar(255),
  user_email  varchar(255),
  user_pass   varchar(255),
  user_level  varchar(10),               -- L1..L7
  user_role   varchar(20),               -- admin|supervisor|agent|client|vip
  user_unit   varchar(255),
  user_div    varchar(255),
  user_parid  varchar(20),               -- parent user ID (USR-xxx)
  user_status varchar(20)                -- active|inactive
) COLLATE=utf8mb4_unicode_ci;
```

### Role Mapping (dari RABC)
| Excel Role | PRISM Role | Keterangan |
|-----------|------------|------------|
| Super Admin | `admin` | Full CRUD semua menu, approval semua transaksi |
| Admin | `supervisor` | CRUD menu tertentu (sesuai level & divisi) |
| Site Admin | `agent` | Read & Update (sesuai level & project berjalan) |
| User | `client` | Read-only (pada project tertentu) |
| VIP | `vip` | View all, tanpa edit operasional |

### Level Mapping → Jabatan
| Level | Jabatan |
|-------|---------|
| L1 | Direktur |
| L2 | Group Head |
| L3 | Division Head |
| L4 | Analyst |
| L5 | Senior Officer |
| L6 | Officer |
| L7 | Staff |

### Laravel Auth Override (`app/Models/User.php`)
- `$incrementing = false`, `$timestamps = false`, `$keyType = 'string'`
- `getAuthPassword()` → return `$this->user_pass`
- `getAuthPasswordName()` → return `'password'` *(key di credentials array, bukan nama kolom DB)*
- Virtual attribute `password` Attribute dengan get/set → map ke kolom `user_pass` (diperlukan untuk fitur rehash Laravel 13)
- `getRememberToken/Name/set` → no-op (kolom tidak ada)
- Helper methods: `isAdmin()`, `isSupervisor()`, `isAgent()`, `isClient()`, `isVip()`, `isActive()`, `jabatan()`

### AuthController credentials array
```php
$credentials = [
    'user_email'  => $request->input('email'),
    'password'    => $request->input('password'),  // harus 'password' agar difilter dari WHERE
    'user_status' => 'active',
];
```

---

## 7. Import Data User (`Daftar User` dari RABC PRISM.xlsx)

10 user diimport dari sheet "Daftar User". Password default: `Prism@[ID Karyawan]`.

| User ID | Nama | Email | Password | Role | Level |
|---------|------|-------|----------|------|-------|
| USR-001 | Yuni Dwi Astuti | *(belum ada)* | `Prism@001` | vip | L1 |
| USR-002 | Andhika Fauza | andhika.fauza@ias.id | `Prism@002` | supervisor | L2 |
| USR-003 | Lugina Prawira | lugina.prawira@ias.id | `Prism@003` | supervisor | L3 |
| USR-004 | Luthfia Eka Putri | luthfia.putri@ias.id | `Prism@004` | supervisor | L5 |
| USR-005 | Uwes Qurny | uwes.qurny@ias.id | `Prism@005` | supervisor | L5 |
| USR-007 | Refki Ruseimy | refki.ruseimy@ias.id | `Prism@006` | supervisor | L2 |
| USR-008 | Muhamad Iqbal | muhamad.iqbal@ias.id | `Prism@007` | admin | L5 |
| USR-009 | Sarminsyah | sarminsyah@ias.id | `Prism@008` | admin | L5 |
| USR-010 | Riandaka Rizal R | riandaka.rizal@ias.id | `Prism@009` | admin | L5 |
| USR-012 | Adityo Nugroho | adityo.nugroho@ias.id | `Prism@010` | client | L7 |

`user_parid` diisi dengan kode USR-xxx atasan langsung sesuai kolom "Atasan Langsung" di Excel.

---

## 8. RBAC — Hak Akses per Role

### Akses Halaman & Fitur
| Fitur | admin | supervisor | vip | client |
|-------|:-----:|:----------:|:---:|:------:|
| Dashboard | ✓ | ✓ | ✓ | ✓ |
| Navbar Project/Vendor/Report | ✓ | ✓ | ✓ | ✗ |
| Monitoring (view) | ✓ | ✓ | ✓ | ✗ |
| Monitoring (add/edit/delete) | ✓ | ✓ | ✗ | ✗ |
| Tickets (lihat) | semua | scope team | semua | milik sendiri |
| Tickets (buat) | ✓ | ✓ | ✓ | ✓ |
| Tickets (update/merge/split) | ✓ | ✓ | ✗ | ✗ |
| Reports | ✓ | ✓ | ✓ | ✗ |
| Admin panel | ✓ | ✗ | ✗ | ✗ |

### Route Middleware
```php
// Monitoring view
Route::middleware('role:supervisor,admin,vip')

// Monitoring write (supervisor ditambahkan)
Route::middleware('role:admin,supervisor')

// Admin panel
Route::middleware('role:admin')
```

### TicketPolicy — VIP read-only
`update()`, `merge()`, `split()` mengembalikan `false` jika `isClient() || isVip()`.

---

## 9. Division Scoping — Monitoring Data

Data `pjct_main` difilter berdasarkan kolom `pjct_div` sesuai unit/divisi user dan hierarki bawahannya.

### Unit → pjct_div Mapping
| user_unit | pjct_div yang bisa diakses |
|-----------|--------------------------|
| Technology Operation & Maintenance | `TC` |
| Equipment Operation & Maintenance | `EQ` |
| Technology Commercial | `TCC`, `TC` |
| Equipment Commercial | `EQC`, `EQ` |

### Aturan Hierarki
- User melihat `pjct_div` dari unit-nya sendiri **+** semua `pjct_div` dari bawahan (rekursif via `user_parid`)
- **VIP** → tidak ada filter (lihat semua divisi)
- User tanpa unit dan tanpa bawahan → tidak ada filter

### Implementasi (`MonitoringController`)
- `allowedDivCodes(User $user): ?array` — return `null` = no filter, array = whitelist
- `subordinateDivCodes(string $userId): Collection` — rekursif lewat `user_parid`
- Filter diterapkan ke query utama **dan** query KPI cards

---

## 10. Layout Fix — Main Wrapper Width

**Problem:** `#main-wrapper` dengan `flex-1` di dalam flex container menyebabkan lebar `100vw + ml-240px` → overflow horizontal.

**Fix:**
```html
<!-- HTML: ganti flex-1 dengan explicit width -->
<div id="main-wrapper" class="flex min-h-full flex-col ml-60 w-[calc(100vw-15rem)]">
```
```js
// JS apply(): set width sesuai state sidebar
mainWrapper.style.width = collapsed ? 'calc(100vw - 4rem)' : 'calc(100vw - 15rem)';
```

---

## 11. Sidebar UX — Avatar Expand

Saat sidebar collapsed, klik avatar (inisial nama user) di footer → expand sidebar.

```html
<button id="sidebar-avatar-btn" ...>{{ substr(user->user_name, 0, 1) }}</button>
```
```js
document.getElementById('sidebar-avatar-btn')?.addEventListener('click', function () {
    if (sidebar.classList.contains('collapsed')) { apply(false); ... }
});
```

---

## 12. Jabatan di Sidebar & Topbar

Subtitle di bawah nama user menampilkan jabatan struktural (dari `user_level`), bukan role PRISM.

Method `jabatan()` di `User.php`:
```php
match ($this->user_level) {
    'L1' => 'Direktur', 'L2' => 'Group Head', 'L3' => 'Division Head',
    'L4' => 'Analyst',  'L5' => 'Senior Officer', 'L6' => 'Officer', 'L7' => 'Staff',
}
```

Digunakan di `layouts/app.blade.php` (sidebar footer + topbar subtitle).
