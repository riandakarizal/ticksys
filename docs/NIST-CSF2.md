# NIST Cybersecurity Framework 2.0 — PRISM

Assessed: 2026-07-05
Application: PRISM (Project & Issue Management System)
Stack: Laravel 13.2 · PHP 8.5 · MySQL · XAMPP · Tailwind CSS
Deployment: Internal (localhost / LAN), target WireGuard VPN

**Status legend**
| Symbol | Meaning |
|--------|---------|
| ✅ | Implemented |
| ⚠️ | Partially implemented — gaps noted |
| ❌ | Not implemented |
| 🔲 | Not applicable to current scope |

---

## 1. GOVERN (GV)

> Establishes and monitors the organization's cybersecurity risk management strategy, expectations, and policy.

### GV.OC — Organizational Context

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| GV.OC-01 | Mission and objectives understood | ⚠️ | PRISM dibangun sebagai internal tool untuk Airport Services. Scope didefinisikan secara informal (S2 portfolio). Belum ada formal mission statement dokumen. |
| GV.OC-02 | Internal and external stakeholders identified | ⚠️ | Role model (admin, supervisor, agent, client) mencerminkan stakeholder. Belum ada stakeholder register formal. |
| GV.OC-03 | Legal and regulatory requirements understood | ❌ | Belum ada mapping ke regulasi (UU PDP Indonesia, ISO 27001, dll). |
| GV.OC-04 | Critical objectives and services identified | ⚠️ | Ticket management dan project monitoring adalah core service. Belum didokumentasikan secara formal sebagai critical asset. |
| GV.OC-05 | Outcomes and risk appetite defined | ❌ | Belum ada risk appetite statement. |

### GV.RM — Risk Management Strategy

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| GV.RM-01 | Risk management policy established | ❌ | Belum ada formal risk management policy. |
| GV.RM-02 | Risk tolerance defined | ❌ | Belum terdefinisi secara eksplisit. |
| GV.RM-03 | Risk management integrated into planning | ⚠️ | SBOM tersedia (`SBOM.md`). Dependency management ada via Composer + npm lock files. Belum ada risk register. |
| GV.RM-06 | Risk response prioritized | ❌ | Belum ada formal prioritization framework. |

### GV.RR — Roles, Responsibilities, and Authorities

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| GV.RR-01 | Cybersecurity roles defined | ⚠️ | Role RBAC (admin/supervisor/agent/client) terdefinisi di `EnsureRole` middleware dan `User` model. Belum ada RACI chart formal. |
| GV.RR-02 | Roles assigned and communicated | ✅ | Role assignment via `admin.users.index`, dikelola admin. Method: `isAdmin()`, `isSupervisor()`, `isAgent()`, `isClient()`. |
| GV.RR-03 | Adequate resources allocated | ⚠️ | Single-developer project. Resource allocation informal. |
| GV.RR-04 | Cybersecurity in HR processes | ❌ | Belum ada onboarding/offboarding security procedure formal. |

### GV.PO — Policy

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| GV.PO-01 | Cybersecurity policy established | ❌ | Belum ada formal security policy document. |
| GV.PO-02 | Policy reviewed and updated | ❌ | N/A — policy belum ada. |

### GV.OV — Oversight

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| GV.OV-01 | Cybersecurity strategy reviewed | ❌ | Belum ada review cycle formal. |
| GV.OV-02 | Results of risk assessments reviewed | ❌ | Belum ada risk assessment yang dilakukan. |
| GV.OV-03 | Performance reviewed | ⚠️ | SLA compliance sebagian dimonitor via `SlaPolicy` model (response_minutes, resolution_minutes). Dashboard menampilkan ticket stats. |

### GV.SC — Supply Chain Risk Management

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| GV.SC-01 | Supply chain risk management established | ⚠️ | SBOM tersedia (`SBOM.md`). Belum ada formal vendor assessment process. |
| GV.SC-02 | Suppliers identified and prioritized | ⚠️ | Dependencies terdokumentasi di SBOM. Satu dependency LGPL-3.0 (`simple-datatables`) sudah dicatat. |
| GV.SC-06 | Supplier security requirements communicated | ❌ | Belum ada vendor security requirements formal. |
| GV.SC-07 | Supply chain risks monitored | ⚠️ | `composer.lock` dan `package-lock.json` pin exact versions. Belum ada automated CVE scanning (Dependabot/Snyk). |

---

## 2. IDENTIFY (ID)

> Helps the organization understand its current cybersecurity risk to systems, people, assets, data, and capabilities.

### ID.AM — Asset Management

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| ID.AM-01 | Physical assets inventoried | ⚠️ | Modul `pjct_main` mencatat proyek fisik (peralatan, kontrak). Tabel `ast_main`/`ast_spec` tersedia untuk aset fisik (belum terintegrasi penuh). |
| ID.AM-02 | Software assets inventoried | ✅ | SBOM tersedia (`SBOM.md`). Composer + npm lock files sebagai source of truth. |
| ID.AM-03 | Network/communication assets inventoried | ❌ | Tidak ada network asset inventory. Deployment plan: WireGuard VPN (belum diimplementasi). |
| ID.AM-04 | Service assets inventoried | ⚠️ | Layanan utama (ticket, monitoring, admin) terdefinisi di `routes/web.php`. Belum ada formal service catalog. |
| ID.AM-05 | Assets prioritized | ❌ | Belum ada klasifikasi aset berdasarkan criticality. |
| ID.AM-07 | Inventories include data types | ⚠️ | Data sensitif: credentials (hashed), ticket data, user PII (name, email, phone, job_title). Belum ada data classification formal. |
| ID.AM-08 | Systems/services mapped to business functions | ⚠️ | PRISM-REDESIGN.md mendokumentasikan navigasi dan modul. Belum ada formal system architecture diagram. |

### ID.RA — Risk Assessment

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| ID.RA-01 | Vulnerabilities identified | ⚠️ | Tidak ada automated scanning. Secara manual: LGPL dependency dicatat, throttle pada login (`throttle:5,1`). |
| ID.RA-02 | Threat intelligence gathered | ❌ | Belum ada threat intelligence process. |
| ID.RA-03 | Internal and external threats identified | ❌ | Belum ada formal threat modeling (STRIDE atau sejenisnya). |
| ID.RA-04 | Potential impacts analyzed | ❌ | Belum ada impact analysis formal. |
| ID.RA-05 | Likelihood of threats estimated | ❌ | Belum ada likelihood estimation. |
| ID.RA-06 | Risks prioritized | ❌ | Belum ada risk register/prioritization. |
| ID.RA-08 | Third-party risk identified | ⚠️ | SBOM mendokumentasikan third-party. Belum ada risk scoring per vendor. |

### ID.IM — Improvement

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| ID.IM-01 | Improvements identified from evaluation | ⚠️ | Perubahan ditrack di `PRISM-REDESIGN.md`. Belum ada formal lessons-learned process. |
| ID.IM-02 | Improvements identified from exercises | ❌ | Belum ada tabletop exercise atau penetration testing. |
| ID.IM-03 | Improvements identified from security events | ⚠️ | ActivityLog tersedia untuk ticket events. Belum ada formal post-incident review process. |
| ID.IM-04 | Improvement plans implemented | ⚠️ | MVP-first approach. Phase roadmap (0→4) terdefinisi di AISS. PRISM improvement bersifat iteratif. |

---

## 3. PROTECT (PR)

> Safeguards to manage the organization's cybersecurity risk.

### PR.AA — Identity Management, Authentication, and Access Control

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| PR.AA-01 | Users and accounts managed | ✅ | `UserController` — admin create/update/delete user. Soft-delete via `is_active` flag. |
| PR.AA-02 | Identities authenticated | ✅ | Laravel `Auth` — session-based authentication. Password hashed (`bcrypt` via `password` cast). |
| PR.AA-03 | Users, services authenticated | ⚠️ | Hanya password. **Tidak ada MFA.** Tidak ada SSO/OAuth. |
| PR.AA-04 | Identity assertions protected | ✅ | CSRF token pada semua form (`@csrf`). Session invalidated on logout/idle. |
| PR.AA-05 | Access permissions managed | ✅ | RBAC via `EnsureRole` middleware. 4 roles: admin, supervisor, agent, client. Route-level enforcement. |
| PR.AA-06 | Access revoked upon termination | ⚠️ | Admin dapat nonaktifkan user (`is_active`). Session idle timeout 30 menit (`EnsureSessionIsActive`). Belum ada force-logout semua sesi aktif. |

### PR.AT — Awareness and Training

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| PR.AT-01 | Personnel trained | ❌ | Belum ada security awareness training program. |
| PR.AT-02 | Privileged users trained | ❌ | Belum ada privileged user security training khusus. |

### PR.DS — Data Security

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| PR.DS-01 | Data at rest protected | ⚠️ | Password di-hash (bcrypt). Session data: `SESSION_ENCRYPT=false` (default). **Database tidak dienkripsi.** MySQL di-host lokal via XAMPP. |
| PR.DS-02 | Data in transit protected | ⚠️ | **Tidak ada HTTPS** (localhost HTTP). Plan deploy via WireGuard VPN. Untuk produksi wajib TLS. `SESSION_SECURE_COOKIE` belum dikonfigurasi. |
| PR.DS-10 | Data deleted when no longer needed | ⚠️ | Soft deletes digunakan (`SoftDeletes` trait pada `PjctMain`). Belum ada data retention policy / purge schedule. |
| PR.DS-11 | Data backed up | ❌ | Tidak ada backup otomatis terdokumentasi. XAMPP MySQL manual backup saja. |

### PR.PS — Platform Security

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| PR.PS-01 | Policies for configuration management | ⚠️ | `.env.example` tersedia. Konfigurasi via environment variables. Belum ada hardening checklist resmi. |
| PR.PS-02 | Software maintained | ⚠️ | Composer + npm digunakan untuk dependency management. Belum ada proses update rutin terjadwal. |
| PR.PS-03 | Hardware/software configurations managed | ⚠️ | XAMPP lokal. Belum ada infrastructure-as-code (IaC). |
| PR.PS-04 | Logs generated | ✅ | Laravel logging (`config/logging.php`). `ActivityLog` model untuk ticket events. Stack traces via Whoops (dev). |
| PR.PS-05 | Access to software development tools managed | ⚠️ | Single developer, tidak ada shared dev environment policy. `APP_DEBUG=true` harus dimatikan di produksi. |
| PR.PS-06 | Secure software development practices used | ⚠️ | Laravel best practices (Eloquent parameterized queries — SQL injection protected). CSRF protection aktif. Belum ada SAST/DAST pipeline. |

### PR.IR — Technology Infrastructure Resilience

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| PR.IR-01 | Networks protected | ⚠️ | Target deployment: WireGuard VPN (belum). Saat ini: LAN lokal. Tidak ada network segmentation. |
| PR.IR-02 | Monitoring technologies maintained | ⚠️ | Laravel Pail untuk log streaming (dev). Belum ada uptime monitoring (UptimeRobot, dll). |
| PR.IR-03 | Backup verified | ❌ | Tidak ada backup verification process. |
| PR.IR-04 | Sufficient resilience to disruption | ❌ | Single-instance XAMPP. Tidak ada failover, load balancing, atau HA setup. |

---

## 4. DETECT (DE)

> Enables timely discovery of cybersecurity events.

### DE.CM — Continuous Monitoring

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| DE.CM-01 | Networks monitored | ❌ | Tidak ada network monitoring (IDS/IPS, flow analysis). |
| DE.CM-02 | Physical environment monitored | 🔲 | Di luar scope aplikasi web. |
| DE.CM-03 | User activity monitored | ⚠️ | `ActivityLog` model mencatat aksi per user pada ticket. Belum ada monitoring untuk login failures, privilege escalation, atau unusual access patterns. |
| DE.CM-06 | External service activity monitored | ❌ | Belum ada monitoring untuk third-party API calls. |
| DE.CM-09 | Computing hardware and software monitored | ❌ | Tidak ada host-level monitoring (CPU, memory, disk). |

### DE.AE — Adverse Event Analysis

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| DE.AE-02 | Potentially adverse events analyzed | ⚠️ | Login throttle (`throttle:5,1`) mencegah brute-force. Laravel exception handler mencatat errors. Belum ada anomaly detection otomatis. |
| DE.AE-03 | Event data correlated | ❌ | Tidak ada SIEM atau log correlation tool. |
| DE.AE-04 | Estimated impact determined | ❌ | Manual — tidak ada automated impact assessment. |
| DE.AE-06 | Alerts created | ⚠️ | `AppNotification` model untuk in-app alerts. Belum ada alerting ke email/Slack untuk security events. |
| DE.AE-07 | Cyber threat intelligence integrated | ❌ | Belum ada threat intel feed. |
| DE.AE-08 | Incidents declared | ⚠️ | Ticket system dapat digunakan sebagai incident declaration tool. Belum ada formal incident declaration threshold. |

---

## 5. RESPOND (RS)

> Actions regarding a detected cybersecurity incident.

### RS.MA — Incident Management

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| RS.MA-01 | Incident response plan exists | ❌ | Belum ada formal Incident Response Plan (IRP). |
| RS.MA-02 | Incidents triaged | ⚠️ | Ticket system memiliki priority levels (low/medium/high/critical) dan SLA enforcement. Dapat digunakan sebagai triage tool. |
| RS.MA-03 | Incidents categorized | ⚠️ | `Category` model + subcategory untuk ticket classification. Belum ada security-specific incident category. |
| RS.MA-04 | Incidents escalated | ⚠️ | Assignment ke agent/supervisor via `assigned_to`. Merge/split ticket tersedia. Belum ada formal escalation matrix. |
| RS.MA-05 | Incidents closed | ✅ | Ticket lifecycle: open → in_progress → pending → resolved → closed. `closed_at` timestamp dicatat. |

### RS.AN — Incident Analysis

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| RS.AN-03 | Analysis performed to establish what occurred | ⚠️ | `TicketMessage` menyimpan kronologi komunikasi. `ActivityLog` mencatat perubahan status. Belum ada forensic log analysis tool. |
| RS.AN-06 | Actions performed during investigation documented | ⚠️ | Ticket messages dan activity log sebagai documentation trail. Belum ada formal investigation template. |
| RS.AN-07 | Incident classification updated | ⚠️ | Status dan priority dapat diupdate via `tickets.update`. Belum ada taxonomy formal (VERIS, dll). |
| RS.AN-08 | Incidents cataloged | ⚠️ | Semua ticket tersimpan di database dengan full history. Belum ada security incident-specific catalog terpisah. |

### RS.CO — Incident Response Reporting and Communication

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| RS.CO-02 | Internal stakeholders notified | ✅ | `AppNotification` — in-app notification saat ticket dibuat/diupdate. Notification feed di topbar. |
| RS.CO-03 | External stakeholders notified | ⚠️ | Belum ada email notification otomatis ke requester/client saat incident. `symfony/mailer` tersedia tapi belum terkonfigurasi. |
| RS.CO-04 | Appropriate authorities notified | ❌ | Belum ada prosedur notifikasi ke otoritas eksternal (BSSN, dll). |
| RS.CO-05 | Voluntary information sharing | ❌ | Belum ada information sharing program. |

### RS.MI — Incident Mitigation

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| RS.MI-01 | Incidents contained | ⚠️ | Soft deletes mencegah data loss. Admin dapat nonaktifkan user. Belum ada automated containment (block IP, revoke token, dll). |
| RS.MI-02 | Incidents eradicated | ❌ | Tidak ada formal eradication procedure. |

---

## 6. RECOVER (RC)

> Actions to restore capabilities or services impaired due to a cybersecurity incident.

### RC.RP — Incident Recovery Plan Execution

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| RC.RP-01 | Recovery plan exists | ❌ | Belum ada Business Continuity Plan (BCP) atau Disaster Recovery Plan (DRP). |
| RC.RP-02 | Recovery plan executed | ❌ | N/A — plan belum ada. |
| RC.RP-03 | Recovery activities communicated | ❌ | Belum ada recovery communication procedure. |
| RC.RP-04 | Recovery plan updated after execution | ❌ | Belum ada. |
| RC.RP-05 | Recovery time and data objectives met | ❌ | RTO/RPO belum didefinisikan. |
| RC.RP-06 | Incidents and recovery documented | ⚠️ | Ticket system dapat digunakan untuk post-incident documentation. Belum ada formal post-mortem template. |

### RC.CO — Incident Recovery Communication

| # | Subcategory | Status | Evidence / Notes |
|---|-------------|--------|-----------------|
| RC.CO-03 | Recovery activities communicated to stakeholders | ⚠️ | In-app notification tersedia. Belum ada communication plan untuk recovery scenario. |
| RC.CO-04 | Public perception managed | 🔲 | Internal tool — tidak ada public communication needed saat ini. |

---

## Ringkasan Maturity

| Function | ✅ | ⚠️ | ❌ | Skor Kasar |
|----------|----|----|-----|-----------|
| GOVERN   | 1  | 7  | 11 | Rendah |
| IDENTIFY | 2  | 8  | 8  | Rendah-Menengah |
| PROTECT  | 5  | 10 | 4  | Menengah |
| DETECT   | 0  | 4  | 6  | Rendah |
| RESPOND  | 1  | 9  | 4  | Rendah-Menengah |
| RECOVER  | 0  | 2  | 5  | Rendah |

> **Overall Maturity: Tier 1 (Partial)** — Praktik keamanan ada tapi sebagian besar reaktif dan informal. Belum ada risk management yang sistematis.

---

## Gap Prioritas Tinggi (Quick Wins)

| # | Gap | Rekomendasi | Effort |
|---|-----|-------------|--------|
| 1 | Tidak ada HTTPS | Aktifkan TLS via Nginx reverse proxy + Let's Encrypt sebelum deploy ke non-localhost | Rendah |
| 2 | Tidak ada MFA | Tambahkan TOTP (Google Authenticator) via `pragmarx/google2fa-laravel` | Menengah |
| 3 | Session cookie tidak secure | Set `SESSION_SECURE_COOKIE=true` dan `SESSION_ENCRYPT=true` di `.env` produksi | Rendah |
| 4 | Tidak ada backup otomatis | Jadwalkan `mysqldump` via Laravel Scheduler + `Storage::disk` | Rendah |
| 5 | `APP_DEBUG=true` di produksi | Set `APP_DEBUG=false`, `APP_ENV=production` saat deploy | Rendah |
| 6 | Tidak ada CVE scanning | Tambahkan `composer audit` dan `npm audit` ke CI/CD pipeline | Rendah |
| 7 | Tidak ada email notification | Konfigurasi SMTP via `symfony/mailer` untuk notifikasi ticket ke requester | Menengah |
| 8 | Tidak ada backup verification | Buat restore test procedure minimal bulanan | Rendah |
