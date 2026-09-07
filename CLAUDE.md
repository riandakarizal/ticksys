# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

PRISM (git remote name `ticksys`) is a Laravel 13 / PHP 8.3 internal ops app combining a helpdesk
ticketing system with project/asset/manpower monitoring for Equipment & Technology divisions.
Server-rendered with Blade + Tailwind v4, vanilla JS (no SPA framework), Vite for assets, MySQL/MariaDB
for storage.

## Commands

```bash
composer run dev      # php artisan serve + queue:listen + vite, concurrently — primary dev entrypoint
php artisan serve      # app only, http://localhost:8000
npm run dev             # vite only (asset watch)
npm run build            # production asset build

composer test           # config:clear + php artisan test (Pest)
php artisan test --filter=TestName
vendor/bin/pest tests/Feature/Monitoring/MonitoringAccessTest.php   # single file

vendor/bin/pint         # Laravel Pint (code style)
```

Tests use Pest (`tests/Pest.php` binds `Tests\TestCase` for the `Feature` suite) with `RefreshDatabase`
per-test via `uses(RefreshDatabase::class)`. Test env runs on in-memory SQLite (`phpunit.xml`), while
dev/prod runs MySQL — avoid MySQL-only SQL (raw `LIKE` with div-prefix matching, JSON functions, etc.) in
code paths exercised by tests.

### Docker (used for migrating to another machine)
```bash
docker compose up --build -d
```
Brings up app + MySQL, auto-imports `docker/db-init/01-prism.sql` on first boot (empty volume only).
Copy the whole `prism/` folder (incl. `.env`, `docker/`) to the target machine; `vendor/`, `node_modules/`,
`public/build` are rebuilt by Docker, don't copy them. See `docs/DOCKER-MIGRATION.md` — the dump must be
regenerated (`mysqldump -u root --default-character-set=utf8mb4 prism > docker/db-init/01-prism.sql`)
after significant schema/data changes, and it does **not** include `docfile/` (see below).

## Architecture

### RBAC — role + division scoping, not a permissions table
There is no roles/permissions package. `users.user_role` is a plain string column checked two ways:
- Route-level: `EnsureRole` middleware (`app/Http/Middleware/EnsureRole.php`), applied as
  `->middleware('role:superadmin,admin,siteadmin,vip')` — comma-separated role list per route group in
  `routes/web.php`. Current roles: `superadmin`, `admin`, `siteadmin`, `user`, `vip` (see
  `App\Models\User::isSuperAdmin()` etc.). Note `docs/PRISM-DATABASE.md` and
  `docs/PRISM-REDESIGN.md` still reference an older role set (`supervisor`/`agent`/`client`) — trust the
  code (`User` model, `EnsureRole` usages) over those docs.
- Data-level: `User::allowedDivCodes()` returns `null` (no filter — vip/superadmin see everything) or an
  array of `pjct_div` codes the user may see, built from `UNIT_DIV_MAP` (their unit → div codes) plus
  `subordinateDivCodes()` (self-referential `users.user_parid` hierarchy — a parent sees everything their
  reports can see). Controllers that touch `pjct_main`-derived data (`MonitoringController`,
  `PjctDocController`) must apply this filter manually; it is not a global scope.

### Custom string primary keys
Several models generate their own PK in a `booted()` → `static::creating()` hook instead of using
auto-increment (e.g. `PjctMain`: `PJ0001`, `PJ0002`... by taking `max(id)` and incrementing the numeric
suffix). When adding new rows to these tables outside Eloquent (raw inserts, seeders, imports), you must
replicate this format manually or the app's assumptions about ID parsing break. `users.id` is similarly a
manual `USR-NNN` string, and `User` overrides `getAuthPasswordName()`/`getAuthPassword()` to authenticate
against the `user_pass` column instead of `password`.

### Document storage (`docfile/`)
Project legal/admin documents (Kontrak, RKST, RAB, BAST, SOP) live as real files on disk under
`docfile/PJxxxx/`, indexed by the `pjct_doc` table (`doc_filepath` relative to the `docfile` disk defined
in `config/filesystems.php`). Served via `monitoring.docs.show` → `PjctDocController@show`, which streams
inline (PDF opens in-browser) and enforces `allowedDivCodes()` the same way monitoring data does.
`docfile/` is intentionally **not** git-tracked (see the "Stop tracking docfile/ in git" history) and is
**not** included in the Docker DB dump — it has to be copied separately when migrating environments.

### Domain areas
- **Tickets** (`TicketController`, `TicketMessageController`, `Support/TicketManager.php`,
  `Support/Helpdesk.php`): helpdesk ticketing — SLA policies, categories, custom fields, merge/split,
  attachments, activity log + in-app notifications.
- **Project monitoring** (`MonitoringController`, `PjctMain`, `PjctDoc`): `pjct_main` is the master
  project record (`pjct_div` = `TC`/`EQ`/`TCC`/`EQC`, `pjct_status` = `UPC`/`OG`/`HVR`/`DLY`/`END`,
  `pjct_type` = `RENT`/`SUPPLY`/`JASA`).
- **Assets** (`AssetController`, `AstMain`): equipment inventory, FK'd to `pjct_main` via `ast_pjctid`.
- **Manpower** (`ManpowerController`, `PjctEmp`): staff assigned per project.
- **Bulk import, preview-then-confirm** (`AssetImportController` + `Support/AssetImportService.php` for
  `ast_main`; `ProjectImportController` + `Support/ProjectImportService.php` for `pjct_main`): two
  superadmin-only flows built on the same shape — `parse()` → `classify()` (tags every row `new` / `skip`
  / `error`) → preview screen → `execute()`. The upload is parked on the `local` disk between the two
  requests (session holds only the path) and re-parsed on confirm, so duplicates are re-checked against
  live data. Both are insert-only — a duplicate is skipped, never updated — and both mass-insert, which
  bypasses Eloquent events: that is why the PKs are reserved in a block up front
  (`AstMain::nextIds()` / `PjctMain::nextIds()`), why the `SystemLog` row is written by hand, and why
  `ProjectImportService::execute()` recreates the `docfile/PJxxxx/` directory that `PjctMain`'s `created`
  hook would normally make. Distinct from the equipment import below, which has its own route names
  (`monitoring.import.*` vs `monitoring.projects.import.*`).
- **Report → Data** (`AssetReportController`, `Support/AssetReportExportService.php`): tarikan mentah
  seluruh kolom `ast_main` (bukan subset operasional seperti `AssetController`) dengan filter gabungan —
  search lintas kolom, Type/Brand/Status/Kondisi/Region/Lokasi/Tahun/Project, dan rentang
  `ast_delvdate`/`ast_purcdate` — plus sorting per kolom (whitelist `SORTABLE`, input di luar itu jatuh ke
  `id`). Tombol export memakai filter & sort yang sedang aktif dan menghasilkan `.xlsx` dua sheet:
  `Data Asset` (kop laporan berisi daftar filter + freeze pane + autofilter) dan `Ringkasan` (rekap per
  status/kondisi/type/region). Baris dibaca lewat `cursor()`, jadi semua query agregat harus selesai
  **sebelum** iterasi dimulai — koneksi MySQL-nya unbuffered saat kursor terbuka. Export penuh ~3.500 baris
  memakan ~78 MB memori; kalau `ast_main` tumbuh jauh lebih besar, writer-nya perlu diganti ke mode
  streaming.
- **Equipment import/export** (`EqtImportController`, `Support/EqtImportService.php`): xlsx-driven bulk
  import/export for equipment projects, handovers, maintenance, vehicles — template-based via
  PhpSpreadsheet, admin-only routes under `/monitoring/import` and `/monitoring/export`.
- **Admin** (`app/Http/Controllers/Admin/*`): devices, users, projects (`Team`), categories, SLA
  policies — superadmin-gated CRUD, separate from the project-monitoring "projects" concept (`Team` here
  is helpdesk-side project grouping, not `pjct_main`).

Full column-by-column schema reference (including the division-scoping unit→div table) is in
`docs/PRISM-DATABASE.md`; treat its role names as stale per above but the table/column documentation as
accurate.
