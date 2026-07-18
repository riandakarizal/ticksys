<?php
/**
 * PRISM Documentation — Excel Generator
 * Run: C:\php85\php.exe docs\generate-excel.php
 * Output: docs\PRISM-DOCUMENTATION.xlsx
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setTitle('PRISM System Documentation')
    ->setSubject('PT IAS Support Indonesia')
    ->setDescription('Project & Issue Management System — Documentation')
    ->setCreator('Iqbal Muhamad');

// ── Color palette ────────────────────────────────────────────────────────────
$TEAL    = '1AB5B5';
$TEAL_LT = 'E6F9F9';
$SLATE   = '475569';
$WHITE   = 'FFFFFF';
$RED_LT  = 'FEE2E2';
$AMB_LT  = 'FEF3C7';
$GRN_LT  = 'DCFCE7';
$BLU_LT  = 'DBEAFE';
$VIO_LT  = 'EDE9FE';

// ── Helper functions ─────────────────────────────────────────────────────────
function styleHeader($sheet, $range, $bgHex = '1AB5B5', $fgHex = 'FFFFFF') {
    $sheet->getStyle($range)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => $fgHex], 'size' => 10],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgHex]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
    ]);
}

function styleRow($sheet, $range, $bgHex = 'FFFFFF') {
    $sheet->getStyle($range)->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgHex]],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
    ]);
}

function styleCell($sheet, $cell, $bgHex) {
    $sheet->getStyle($cell)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB($bgHex);
}

function titleRow($sheet, $text, $mergeTo, $color = '1AB5B5') {
    $sheet->setCellValue('A1', $text);
    $sheet->mergeCells("A1:{$mergeTo}1");
    $sheet->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(28);
}

// ════════════════════════════════════════════════════════════════════════════
// SHEET 1 — Risk Register
// ════════════════════════════════════════════════════════════════════════════
$ws = $spreadsheet->getActiveSheet()->setTitle('Risk Register');

titleRow($ws, '  PRISM — Risk Register  |  PT IAS Support Indonesia', 'N');

$headers = ['Risk ID','CSF Function','CSF Category','Deskripsi Risiko','Sumber Ancaman','Kerentanan','Likelihood','Impact','Risk Score','Risk Level','Kontrol yang Ada','Rekomendasi Mitigasi','Prioritas','Status'];
$ws->fromArray($headers, null, 'A2');
styleHeader($ws, 'A2:N2');
$ws->getRowDimension(2)->setRowHeight(22);

$risks = [
    ['R-01','PROTECT','PR.DS-02','Data komunikasi antara client-server tidak terenkripsi karena tidak ada HTTPS. Kredensial dan session token dapat disadap di jaringan.','Attacker di jaringan yang sama (LAN/WiFi)','Tidak ada TLS/SSL. HTTP plain text. SESSION_SECURE_COOKIE belum diset.',3,5,15,'High','Deployment masih localhost/LAN','Pasang TLS via Nginx reverse proxy + Let\'s Encrypt. Set SESSION_SECURE_COOKIE=true di .env produksi.',1,'Open'],
    ['R-02','PROTECT','PR.AA-03','Autentikasi hanya menggunakan password tunggal. Jika password bocor, akun langsung dapat diakses tanpa faktor kedua.','External attacker, insider threat','Tidak ada Multi-Factor Authentication (MFA)',3,5,15,'High','Login throttle 5 percobaan/menit','Implementasi TOTP (Google Authenticator) via pragmarx/google2fa-laravel, wajib untuk admin dan supervisor.',1,'Open'],
    ['R-03','PROTECT','PR.DS-01','Data sensitif di database (PII user, detail tiket, nilai kontrak proyek) tidak dienkripsi at-rest.','Insider threat, akses fisik ke server','MySQL tanpa enkripsi at-rest. XAMPP lokal tanpa hardening.',2,5,10,'High','Password di-hash bcrypt','Aktifkan MySQL TDE atau enkripsi field sensitif di application layer via Laravel encrypted cast.',2,'Open'],
    ['R-04','PROTECT','PR.PS-05','APP_DEBUG=true aktif. Stack trace lengkap terbuka ke publik jika terkena error di lingkungan non-localhost.','Attacker yang sengaja memicu error','Konfigurasi debug mode untuk development dipakai di semua environment',3,4,12,'High','Hanya di localhost saat ini','Set APP_DEBUG=false dan APP_ENV=production sebelum deploy.',1,'Open'],
    ['R-05','RECOVER','RC.RP-01','Tidak ada prosedur backup database otomatis. Kegagalan hardware dapat menyebabkan kehilangan data permanen.','Hardware failure, human error, ransomware','Tidak ada backup schedule. Single-instance XAMPP tanpa redundancy.',2,5,10,'High','Soft deletes pada beberapa model','Jadwalkan mysqldump otomatis via Laravel Scheduler. Simpan backup ke lokasi berbeda (offsite/cloud).',2,'Open'],
    ['R-06','IDENTIFY','ID.RA-01','Dependency pihak ketiga tidak dipindai secara otomatis untuk CVE. Kerentanan baru tidak terdeteksi.','Supply chain attack, known CVE exploit','Tidak ada automated vulnerability scanning (Dependabot, Snyk, composer audit)',3,4,12,'High','Lock file pin versi. SBOM tersedia.','Tambahkan composer audit dan npm audit ke CI/CD. Aktifkan Dependabot.',2,'Open'],
    ['R-07','PROTECT','PR.AA-06','Tidak ada mekanisme force-logout semua sesi aktif saat user dinonaktifkan.','Insider threat, akun dikompromikan','is_active flag dicheck di login, bukan di setiap request',2,4,8,'Medium','Idle timeout 30 menit','Tambahkan pengecekan is_active di middleware atau revoke semua sesi saat user dinonaktifkan.',3,'Open'],
    ['R-08','DETECT','DE.CM-03','Aktivitas login gagal dan perubahan privilege tidak dilog secara khusus. Serangan brute-force lambat sulit terdeteksi.','External attacker, insider threat','ActivityLog hanya mencatat event tiket, bukan security events',2,4,8,'Medium','Login throttle 5 percobaan/menit','Tambahkan logging untuk: login failed, login berhasil, perubahan role, akses ditolak (403).',3,'Open'],
    ['R-09','PROTECT','PR.IR-04','PRISM berjalan di single-instance XAMPP tanpa failover. Server mati = aplikasi tidak dapat diakses.','Hardware failure, power outage, OS crash','Single point of failure. Tidak ada load balancing.',3,3,9,'Medium','—','Dokumentasikan RTO/RPO. Rencanakan hot-standby. Gunakan process manager (Supervisor) agar auto-restart.',4,'Open'],
    ['R-10','GOVERN','GV.SC-07','simple-datatables (LGPL-3.0): jika dimodifikasi dan dipakai komersial, perubahan wajib dirilis ulang di bawah LGPL.','Legal/compliance risk','Penggunaan library LGPL tanpa dokumentasi compliance',1,3,3,'Low','Tercatat di SBOM.md','Dokumentasikan bahwa simple-datatables tidak dimodifikasi. Pertimbangkan alternatif MIT jika perlu modifikasi.',5,'Open'],
    ['R-11','PROTECT','PR.DS-10','Tidak ada data retention policy. Data lama terakumulasi tanpa penghapusan terjadwal, meningkatkan blast radius jika breach.','Data breach, compliance violation','Tidak ada purge schedule atau retention policy',2,3,6,'Medium','Soft deletes tersedia','Definisikan retention period per tipe data. Implementasi via Laravel Scheduler.',4,'Open'],
    ['R-12','RESPOND','RS.MA-01','Tidak ada Incident Response Plan (IRP) formal. Tim tidak memiliki panduan langkah-langkah respons insiden.','Semua ancaman keamanan','Tidak ada IRP, RACI chart, atau runbook',2,4,8,'Medium','Ticket system dapat digunakan ad-hoc','Buat IRP minimal: definisi insiden, eskalasi, containment steps, komunikasi, dan post-mortem template.',3,'Open'],
    ['R-13','PROTECT','PR.IR-01','PRISM belum di-deploy di balik WireGuard VPN. Endpoint berpotensi terekspos ke jaringan lebih luas.','Network-based attacker','Deployment plan VPN belum diimplementasi',2,4,8,'Medium','Saat ini akses terbatas localhost/LAN','Implementasi WireGuard VPN sesuai rencana arsitektur sebelum deployment ke lingkungan non-lokal.',2,'Open'],
    ['R-14','DETECT','DE.AE-03','Tidak ada log correlation atau SIEM. Serangan multi-tahap tidak terdeteksi.','Advanced attacker','Tidak ada SIEM, tidak ada alerting berbasis anomali',1,4,4,'Low','Laravel log channel tersedia','Agregasi log ke file terpusat. Jangka panjang: integrasikan ke Grafana Loki atau ELK Stack.',5,'Open'],
    ['R-15','PROTECT','PR.AT-01','Tidak ada security awareness training untuk pengguna PRISM. Rentan social engineering dan phishing.','Social engineering, phishing','Tidak ada training program',2,3,6,'Medium','—','Buat panduan keamanan singkat untuk user: password policy, phishing awareness, pelaporan insiden.',4,'Open'],
    ['R-16','RESPOND','RS.CO-03','symfony/mailer tersedia tapi belum dikonfigurasi. Notifikasi insiden hanya via in-app — delayed jika user tidak aktif.','Delayed incident response','Konfigurasi SMTP belum ada di .env',2,3,6,'Medium','In-app notification via AppNotification','Konfigurasi SMTP di .env. Tambahkan email notification untuk tiket dibuat, diupdate, SLA approaching.',4,'Open'],
    ['R-17','GOVERN','GV.RR-04','Tidak ada prosedur onboarding/offboarding keamanan. User baru tidak mendapat briefing; user keluar tidak langsung di-revoke.','Insider threat, human error','Tidak ada HR-security integration',2,3,6,'Medium','Admin dapat nonaktifkan user manual','Buat checklist onboarding/offboarding: aktivasi akun, role assignment, briefing, revokasi saat keluar.',4,'Open'],
    ['R-18','IDENTIFY','ID.AM-07','Data PII (nama, email, telepon, jabatan) disimpan tanpa klasifikasi formal.','Data breach, compliance violation','Tidak ada data classification scheme',2,3,6,'Medium','RBAC membatasi akses per role','Buat data classification: Public / Internal / Confidential / Restricted.',5,'Open'],
    ['R-19','RECOVER','RC.RP-05','RTO dan RPO belum didefinisikan. Tidak ada target berapa lama sistem boleh down atau berapa data yang boleh hilang.','Semua insiden downtime','Tidak ada BCP/DRP',2,3,6,'Medium','—','Definisikan RTO dan RPO berdasarkan kebutuhan operasional. Dokumentasikan di DRP.',5,'Open'],
    ['R-20','PROTECT','PR.PS-06','Tidak ada SAST atau code review keamanan terjadwal. Kerentanan di kode baru bisa lolos ke produksi.','Developer error, insider threat','Tidak ada SAST pipeline, tidak ada security code review checklist',2,4,8,'Medium','Eloquent ORM mencegah SQL injection. CSRF aktif.','Tambahkan phpstan/phpstan ke pipeline. Lakukan security review sebelum merge fitur baru.',3,'Open'],
];

$levelColors = ['High' => 'FEE2E2', 'Medium' => 'FEF3C7', 'Low' => 'DCFCE7', 'Critical' => 'FCE7F3'];

foreach ($risks as $i => $r) {
    $row = $i + 3;
    $ws->fromArray($r, null, "A{$row}");
    $bg = ($i % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
    styleRow($ws, "A{$row}:N{$row}", $bg);
    // Color risk level cell
    styleCell($ws, "J{$row}", $levelColors[$r[9]] ?? 'FFFFFF');
    $ws->getStyle("J{$row}")->getFont()->setBold(true);
    $ws->getRowDimension($row)->setRowHeight(50);
}

// Column widths
$widths = ['A'=>9,'B'=>10,'C'=>13,'D'=>45,'E'=>28,'F'=>32,'G'=>12,'H'=>10,'I'=>12,'J'=>11,'K'=>30,'L'=>38,'M'=>11,'N'=>10];
foreach ($widths as $col => $w) {
    $ws->getColumnDimension($col)->setWidth($w);
}
$ws->freezePane('A3');

// ════════════════════════════════════════════════════════════════════════════
// SHEET 2 — Functional Requirements
// ════════════════════════════════════════════════════════════════════════════
$ws2 = $spreadsheet->createSheet()->setTitle('Functional Requirements');

titleRow($ws2, '  PRISM — Functional Requirements', 'E');
$ws2->fromArray(['FR-ID','Modul','Deskripsi','Role','Status'], null, 'A2');
styleHeader($ws2, 'A2:E2');
$ws2->getRowDimension(2)->setRowHeight(22);

$frs = [
    ['FR-01','Auth','Login dengan email dan password','Semua','✅ Done'],
    ['FR-02','Auth','Session auto-logout setelah 30 menit idle','Semua','✅ Done'],
    ['FR-03','Auth','Rate limiting login: maks 5 percobaan per menit','Semua','✅ Done'],
    ['FR-04','Auth','Logout manual dengan konfirmasi dialog','Semua','✅ Done'],
    ['FR-05','Dashboard','Tampilkan KPI proyek: total, OG, DLY, HVR, nilai kontrak','Admin, Supervisor','✅ Done'],
    ['FR-06','Dashboard','Tampilkan grafik distribusi status, tipe, dan tahun proyek','Admin, Supervisor','✅ Done'],
    ['FR-07','Dashboard','Tampilkan distribusi proyek per area','Admin, Supervisor','✅ Done'],
    ['FR-08','Dashboard','Tampilkan quick-stats tiket: open, in_progress, pending, resolved','Semua','✅ Done'],
    ['FR-09','Dashboard','Tampilkan tabel 8 proyek terbaru','Admin, Supervisor','✅ Done'],
    ['FR-10','Tiket','Buat tiket baru (subject, deskripsi, kategori, subkategori, priority, attachment)','Semua','✅ Done'],
    ['FR-11','Tiket','Lihat daftar tiket dengan filter status, priority, kategori, pencarian','Semua (scope role)','✅ Done'],
    ['FR-12','Tiket','Update status tiket (open/in_progress/pending/resolved/closed)','Agent, Supervisor, Admin','✅ Done'],
    ['FR-13','Tiket','Assign tiket ke agent atau tim','Supervisor, Admin','✅ Done'],
    ['FR-14','Tiket','Kirim dan terima pesan di dalam tiket','Semua','✅ Done'],
    ['FR-15','Tiket','Upload dan download attachment tiket','Semua','✅ Done'],
    ['FR-16','Tiket','Merge dua tiket menjadi satu','Supervisor, Admin','✅ Done'],
    ['FR-17','Tiket','Split satu tiket menjadi beberapa tiket','Supervisor, Admin','✅ Done'],
    ['FR-18','Tiket','SLA enforcement: tampilkan response_due_at dan resolution_due_at','Sistem otomatis','✅ Done'],
    ['FR-19','Tiket','Auto-close tiket resolved yang lama tanpa aktivitas','Sistem otomatis','✅ Done'],
    ['FR-20','Monitoring','Tampilkan daftar proyek dengan filter tahun, status, tipe, pencarian','Supervisor, Admin','✅ Done'],
    ['FR-21','Monitoring','Tambah, edit, hapus (soft-delete) data proyek','Admin','✅ Done'],
    ['FR-22','Monitoring','Restore proyek yang di-archive','Admin','✅ Done'],
    ['FR-23','Monitoring','Tampilkan 4 KPI card: total, OG, DLY, total nilai kontrak','Supervisor, Admin','✅ Done'],
    ['FR-24','Notifikasi','Kirim notifikasi in-app saat tiket dibuat, diupdate, atau mendekati SLA deadline','Sistem otomatis','✅ Done'],
    ['FR-25','Notifikasi','Hapus notifikasi individual','Semua','✅ Done'],
    ['FR-26','Admin','CRUD pengguna dengan role dan assignment tim','Admin','✅ Done'],
    ['FR-27','Admin','CRUD kategori tiket (2 level: parent + child)','Admin','✅ Done'],
    ['FR-28','Admin','CRUD SLA Policy (response & resolution time per policy)','Admin','✅ Done'],
    ['FR-29','Admin','CRUD tim/proyek internal (buat tim, assign lead & member)','Admin','✅ Done'],
    ['FR-30','Laporan','Export data tiket ke CSV','Supervisor, Admin','✅ Done'],
];

foreach ($frs as $i => $r) {
    $row = $i + 3;
    $ws2->fromArray($r, null, "A{$row}");
    styleRow($ws2, "A{$row}:E{$row}", $i % 2 === 0 ? 'F8FAFC' : 'FFFFFF');
    $ws2->getRowDimension($row)->setRowHeight(30);
}
foreach (['A'=>9,'B'=>16,'C'=>60,'D'=>22,'E'=>12] as $c => $w) $ws2->getColumnDimension($c)->setWidth($w);
$ws2->freezePane('A3');

// ════════════════════════════════════════════════════════════════════════════
// SHEET 3 — Non-Functional Requirements
// ════════════════════════════════════════════════════════════════════════════
$ws3 = $spreadsheet->createSheet()->setTitle('Non-Functional Requirements');

titleRow($ws3, '  PRISM — Non-Functional Requirements', 'E');
$ws3->fromArray(['NFR-ID','Kategori','Deskripsi','Target','Status'], null, 'A2');
styleHeader($ws3, 'A2:E2');
$ws3->getRowDimension(2)->setRowHeight(22);

$nfrs = [
    ['NFR-01','Performance','Waktu load halaman dashboard','< 2 detik pada jaringan LAN','✅ Done'],
    ['NFR-02','Performance','Query database dengan index yang tepat untuk tabel tiket dan proyek','Query time < 500ms','✅ Done'],
    ['NFR-03','Security','Seluruh password disimpan dalam bentuk hash','bcrypt (Laravel default)','✅ Done'],
    ['NFR-04','Security','CSRF token pada semua form','Laravel CSRF middleware aktif','✅ Done'],
    ['NFR-05','Security','Akses endpoint dibatasi per role','EnsureRole middleware','✅ Done'],
    ['NFR-06','Security','Session expire setelah 30 menit idle','EnsureSessionIsActive middleware','✅ Done'],
    ['NFR-07','Security','Rate limiting pada endpoint login','5 request/menit','✅ Done'],
    ['NFR-08','Availability','Sistem tersedia selama jam operasional (07.00–22.00)','Uptime > 99% jam operasional','⚠️ Partial'],
    ['NFR-09','Maintainability','Kode mengikuti Laravel conventions dan PSR-12','Enforced via laravel/pint','✅ Done'],
    ['NFR-10','Scalability','Sistem dapat menangani hingga 50 pengguna concurrent','Arsitektur single-server dengan caching','⚠️ Partial'],
    ['NFR-11','Usability','UI responsif untuk desktop dan tablet','Tailwind CSS responsive breakpoints','✅ Done'],
    ['NFR-12','Usability','Navigasi dapat diakses dalam maksimal 3 klik dari halaman manapun','Left sidebar dengan submenu','✅ Done'],
    ['NFR-13','Compatibility','Browser: Chrome, Edge, Firefox versi terbaru','Tested di Chrome 150+','✅ Done'],
    ['NFR-14','Data Integrity','Soft delete pada data proyek (tidak dihapus permanen)','SoftDeletes trait Laravel','✅ Done'],
    ['NFR-15','Audit','Setiap perubahan tiket tercatat di activity log','ActivityLog model','✅ Done'],
];

foreach ($nfrs as $i => $r) {
    $row = $i + 3;
    $ws3->fromArray($r, null, "A{$row}");
    styleRow($ws3, "A{$row}:E{$row}", $i % 2 === 0 ? 'F8FAFC' : 'FFFFFF');
    $ws3->getRowDimension($row)->setRowHeight(28);
}
foreach (['A'=>9,'B'=>16,'C'=>58,'D'=>32,'E'=>12] as $c => $w) $ws3->getColumnDimension($c)->setWidth($w);
$ws3->freezePane('A3');

// ════════════════════════════════════════════════════════════════════════════
// SHEET 4 — Stakeholder & Use Cases
// ════════════════════════════════════════════════════════════════════════════
$ws4 = $spreadsheet->createSheet()->setTitle('Stakeholder & Use Cases');

titleRow($ws4, '  PRISM — Stakeholder Analysis & Use Case Mapping', 'E');

// Stakeholder section
$ws4->setCellValue('A2', 'STAKEHOLDER ANALYSIS');
$ws4->mergeCells('A2:E2');
$ws4->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '475569']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']]]);

$ws4->fromArray(['Role','Label di PRISM','Jabatan Contoh','Kebutuhan Utama','Hak Akses'], null, 'A3');
styleHeader($ws4, 'A3:E3');

$stakeholders = [
    ['System Administrator','admin','IT Admin / Manajer Sistem','Full control: user, kategori, SLA, semua data','Semua fitur termasuk /admin/*'],
    ['Supervisor / Manajer','supervisor','Manajer Area / Kepala Divisi','Monitor proyek & tiket, baca laporan','Dashboard, monitoring, laporan, tiket (baca + update)'],
    ['Agent / Staf Operasional','agent','Staf Teknis / Operasional','Handle tiket yang di-assign, update status','Tiket yang relevan, tidak akses admin/monitoring'],
    ['Client / Pengguna Internal','client','Staf Non-Teknis / Klien','Submit permintaan, track status tiket sendiri','Hanya tiket yang dibuat sendiri'],
];

foreach ($stakeholders as $i => $r) {
    $row = $i + 4;
    $ws4->fromArray($r, null, "A{$row}");
    styleRow($ws4, "A{$row}:E{$row}", $i % 2 === 0 ? 'F8FAFC' : 'FFFFFF');
    $ws4->getRowDimension($row)->setRowHeight(28);
}

// Use Case section
$ws4->setCellValue('A9', 'USE CASE / ACTOR-GOAL MAPPING');
$ws4->mergeCells('A9:E9');
$ws4->getStyle('A9')->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '475569']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']]]);

$ws4->fromArray(['UC-ID','Actor','Use Case','Deskripsi','Status'], null, 'A10');
styleHeader($ws4, 'A10:E10');

$ucs = [
    ['UC-A01','Admin','Kelola Pengguna','Tambah, edit, nonaktifkan, assign role & tim','✅ Done'],
    ['UC-A02','Admin','Kelola Kategori Tiket','Hierarki 2 level, warna per kategori','✅ Done'],
    ['UC-A03','Admin','Kelola SLA Policy','Response & resolution minutes per policy','✅ Done'],
    ['UC-A04','Admin','Kelola Tim / Proyek Internal','Buat tim, assign lead, assign member','✅ Done'],
    ['UC-A05','Admin','Kelola Proyek Kontrak','CRUD di monitoring (pjct_main)','✅ Done'],
    ['UC-A06','Admin','Restore Data Soft-Delete','Pulihkan proyek yang di-archive','✅ Done'],
    ['UC-S01','Supervisor','Monitor Seluruh Tiket','Lihat semua tiket, filter, search','✅ Done'],
    ['UC-S02','Supervisor','Monitor Portofolio Proyek','Status, nilai kontrak, area, tipe real-time','✅ Done'],
    ['UC-S03','Supervisor','Assign / Reassign Tiket','Tentukan agent yang handle tiket','✅ Done'],
    ['UC-S04','Supervisor','Export Laporan','Export data tiket ke CSV','✅ Done'],
    ['UC-S05','Supervisor','Eskalasi Tiket','Merge atau split tiket','✅ Done'],
    ['UC-AG01','Agent','Lihat & Update Tiket','Tiket yang di-assign, update status','✅ Done'],
    ['UC-AG02','Agent','Komunikasi di Tiket','Balas pesan, kirim attachment','✅ Done'],
    ['UC-C01','Client','Submit Tiket Baru','Subject, deskripsi, kategori, attachment','✅ Done'],
    ['UC-C02','Client','Track Status Tiket','Lihat status dan riwayat tiket sendiri','✅ Done'],
    ['UC-C03','Client','Balas Pesan Tiket','Komunikasi dengan agent','✅ Done'],
    ['UC-C04','Client','Terima Notifikasi','In-app notif saat tiket diupdate','✅ Done'],
];

foreach ($ucs as $i => $r) {
    $row = $i + 11;
    $ws4->fromArray($r, null, "A{$row}");
    styleRow($ws4, "A{$row}:E{$row}", $i % 2 === 0 ? 'F8FAFC' : 'FFFFFF');
    $ws4->getRowDimension($row)->setRowHeight(26);
}
foreach (['A'=>10,'B'=>13,'C'=>25,'D'=>52,'E'=>12] as $c => $w) $ws4->getColumnDimension($c)->setWidth($w);
$ws4->freezePane('A11');

// ════════════════════════════════════════════════════════════════════════════
// SHEET 5 — Testing (UAT)
// ════════════════════════════════════════════════════════════════════════════
$ws5 = $spreadsheet->createSheet()->setTitle('Testing UAT');

titleRow($ws5, '  PRISM — UAT Test Cases', 'F');
$ws5->fromArray(['TC-ID','Skenario','Role','Steps','Expected Result','Status'], null, 'A2');
styleHeader($ws5, 'A2:F2');
$ws5->getRowDimension(2)->setRowHeight(22);

$tcs = [
    ['TC-01','Login dengan kredensial valid','Semua','1. Buka /login\n2. Isi email & password yang benar\n3. Klik Login','Redirect ke /dashboard','⬜ Belum diuji'],
    ['TC-02','Login dengan password salah 6x','Semua','1. Isi email benar, password salah\n2. Submit 6 kali','Blocked 1 menit (throttle 5/menit)','⬜ Belum diuji'],
    ['TC-03','Idle 31 menit lalu akses halaman','Semua','1. Login\n2. Tidak ada aktivitas 31 menit\n3. Akses halaman apapun','Redirect ke login, pesan session expired','⬜ Belum diuji'],
    ['TC-04','Client coba akses /monitoring','client','1. Login sebagai client\n2. Akses URL /monitoring','403 Forbidden','⬜ Belum diuji'],
    ['TC-05','Agent coba akses /admin/users','agent','1. Login sebagai agent\n2. Akses URL /admin/users','403 Forbidden','⬜ Belum diuji'],
    ['TC-06','Admin buat tiket baru','admin','1. Buka /tickets/create\n2. Isi semua field\n3. Submit','Tiket muncul di list, notifikasi terbuat','⬜ Belum diuji'],
    ['TC-07','Supervisor filter proyek by status OG','supervisor','1. Buka /monitoring\n2. Pilih filter Status = OG','Hanya proyek On Going tampil','⬜ Belum diuji'],
    ['TC-08','Admin tambah proyek baru via modal','admin','1. Buka /monitoring\n2. Klik Tambah Proyek\n3. Isi form, submit','Proyek muncul di tabel, KPI diupdate','⬜ Belum diuji'],
    ['TC-09','Soft-delete proyek','admin','1. Buka /monitoring\n2. Klik hapus di proyek tertentu','Proyek hilang dari list, bisa di-restore','⬜ Belum diuji'],
    ['TC-10','Merge dua tiket','supervisor','1. Buka tiket A\n2. Klik Merge\n3. Pilih tiket B','Tiket B ditutup, pesan digabung ke A','⬜ Belum diuji'],
    ['TC-11','SLA breach — melewati resolution_due_at','Sistem','1. Buat tiket dengan SLA ketat\n2. Biarkan melewati deadline','Badge SLA breached tampil di tiket','⬜ Belum diuji'],
    ['TC-12','Logout via sidebar','Semua','1. Klik tombol logout di sidebar\n2. Konfirmasi','Session dihapus, redirect ke login','⬜ Belum diuji'],
];

foreach ($tcs as $i => $r) {
    $row = $i + 3;
    $ws5->fromArray($r, null, "A{$row}");
    styleRow($ws5, "A{$row}:F{$row}", $i % 2 === 0 ? 'F8FAFC' : 'FFFFFF');
    $ws5->getStyle("D{$row}")->getAlignment()->setWrapText(true);
    $ws5->getRowDimension($row)->setRowHeight(50);
}
foreach (['A'=>9,'B'=>38,'C'=>14,'D'=>40,'E'=>34,'F'=>14] as $c => $w) $ws5->getColumnDimension($c)->setWidth($w);
$ws5->freezePane('A3');

// ════════════════════════════════════════════════════════════════════════════
// SHEET 6 — Roadmap & Future Enhancement
// ════════════════════════════════════════════════════════════════════════════
$ws6 = $spreadsheet->createSheet()->setTitle('Roadmap');

titleRow($ws6, '  PRISM — Development Roadmap & Future Enhancement', 'E');

// Roadmap
$ws6->setCellValue('A2', 'DEVELOPMENT ROADMAP');
$ws6->mergeCells('A2:E2');
$ws6->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '475569']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']]]);

$ws6->fromArray(['Phase','Nama','Deskripsi','Komponen Utama','Status'], null, 'A3');
styleHeader($ws6, 'A3:E3');

$roadmap = [
    ['Phase 0','Foundation','Setup Laravel, autentikasi, RBAC, schema database dasar','Users, Tickets, Categories, SLA Policies, Teams','✅ Done'],
    ['Phase 1','Project Monitoring','Import data proyek dari CSV, model PjctMain, monitoring page','pjct_main (66 records), MonitoringController, KPI cards','✅ Done'],
    ['Phase 2','UI Redesign','Migrasi layout ke left sidebar, dashboard project-centric, stub pages','layouts/app.blade.php, dashboard.blade.php, 6 stub pages','✅ Done'],
    ['Phase 3','Modul Lanjutan','Asset management, manpower, vendor, report terstruktur','ast_main, ast_spec, vendor tables, report module','🔲 Planned'],
    ['Phase 4','Hardening & Deploy','HTTPS, WireGuard VPN, email notification, backup, MFA','Nginx TLS, SMTP, mysqldump scheduler, TOTP','🔲 Planned'],
];

$phaseColors = ['✅ Done' => 'DCFCE7', '🔲 Planned' => 'F1F5F9', '⚠️ Partial' => 'FEF3C7'];
foreach ($roadmap as $i => $r) {
    $row = $i + 4;
    $ws6->fromArray($r, null, "A{$row}");
    styleRow($ws6, "A{$row}:E{$row}", $phaseColors[$r[4]] ?? 'FFFFFF');
    $ws6->getRowDimension($row)->setRowHeight(36);
}

// Future Enhancement
$ws6->setCellValue('A10', 'FUTURE ENHANCEMENT');
$ws6->mergeCells('A10:E10');
$ws6->getStyle('A10')->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '475569']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']]]);

$ws6->fromArray(['FE-ID','Enhancement','Modul','Estimasi Effort','Prioritas'], null, 'A11');
styleHeader($ws6, 'A11:E11');

$fe = [
    ['FE-01','Asset Management — integrasi ast_main + ast_spec (join brand+model)','Project → Asset','Medium','High'],
    ['FE-02','Manpower tracking per proyek (SDM, jam kerja, posisi)','Project → Manpower','Medium','High'],
    ['FE-03','Master Vendor + riwayat kontrak vendor','Vendor','Medium','Medium'],
    ['FE-04','Laporan Issues terstruktur (tren, kategori, resolusi)','Report → Issues','Medium','Medium'],
    ['FE-05','Laporan Expenses per proyek dengan breakdown','Report → Expenses','Medium','Medium'],
    ['FE-06','Email notification untuk SLA warning dan tiket update','System-wide','Low','High'],
    ['FE-07','MFA (TOTP) untuk role admin dan supervisor','Auth','Low','High'],
    ['FE-08','Export laporan proyek ke Excel via PhpSpreadsheet','Monitoring','Low','Medium'],
    ['FE-09','Kalender / timeline view proyek (Gantt sederhana)','Dashboard','High','Low'],
    ['FE-10','Mobile-responsive optimal untuk smartphone','UI','Medium','Low'],
    ['FE-11','AISS Integration — AI chatbot query data PRISM via RAG','AISS (out of scope)','High','Low'],
    ['FE-12','Audit log viewer di admin panel','Admin','Low','Medium'],
    ['FE-13','Dark mode toggle','UI','Low','Low'],
];

foreach ($fe as $i => $r) {
    $row = $i + 12;
    $ws6->fromArray($r, null, "A{$row}");
    styleRow($ws6, "A{$row}:E{$row}", $i % 2 === 0 ? 'F8FAFC' : 'FFFFFF');
    $ws6->getRowDimension($row)->setRowHeight(28);
}
foreach (['A'=>9,'B'=>52,'C'=>22,'D'=>16,'E'=>12] as $c => $w) $ws6->getColumnDimension($c)->setWidth($w);

// ════════════════════════════════════════════════════════════════════════════
// Write file
// ════════════════════════════════════════════════════════════════════════════
$spreadsheet->setActiveSheetIndex(0);

$outputPath = __DIR__ . '/PRISM-DOCUMENTATION.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($outputPath);

echo "✅ File generated: {$outputPath}\n";
echo "   Sheets: Risk Register, Functional Requirements, Non-Functional Requirements, Stakeholder & Use Cases, Testing UAT, Roadmap\n";
