# Migrasi PRISM ke Cloud (prism.iassupport.id)

## Strategi: build lokal, push ke Docker Hub, pull di server
Image `app` di-build di PC ini dan di-push ke Docker Hub (`iassprism/prism-app`). Server
**tidak perlu** clone repo atau punya toolchain build (composer/npm/php) — cukup 4 file
kecil + folder `docfile/`, lalu `docker compose pull && up -d`.

## Prasyarat di server
- Docker + Docker Compose plugin sudah terinstall (sudah beres).
- DNS `prism.iassupport.id` → A record ke IP server ini.
- Port `80` dan `443` terbuka di firewall/security group (dipakai Caddy untuk HTTP-01
  Let's Encrypt challenge + HTTPS).
- `docker login` di server dengan akun `iassprism` (atau akun lain yang punya akses pull),
  **wajib kalau repo image di Docker Hub statusnya private**.

## File yang perlu ada di server (folder `~/prism/`)
Cukup transfer file-file ini dari PC lokal (scp/rsync) — bukan seluruh source code:
- `docker-compose.prod.yml` — stack production: `caddy` (reverse proxy + auto HTTPS),
  `app` (`image: iassprism/prism-app:latest`, pull dari Docker Hub, tidak expose port ke
  host langsung), `db` (MySQL 8.0 dengan root password asli, bukan
  `MYSQL_ALLOW_EMPTY_PASSWORD` seperti di compose lokal).
- `Caddyfile` — domain `prism.iassupport.id`, proxy ke `app:8080`. **Isi dulu email di
  baris `email your-email@example.com` sebelum deploy** (dipakai Let's Encrypt untuk
  notifikasi expiry sertifikat).
- `.env.production` — **isi `DB_PASSWORD` dengan password kuat sebelum dipakai**, masih
  placeholder `CHANGE_ME_STRONG_PASSWORD`. `APP_KEY` sudah digenerate baru (beda dari
  `.env` lokal).
- `docker/db-init/01-prism.sql` — dump database, baru di-regenerate hari ini.
- `docfile/` — folder dokumen legal proyek (tidak ikut dump SQL maupun image).

## Langkah di PC lokal (build & push image)
Pakai `docker buildx build --platform linux/amd64,linux/arm64` (bukan `docker build` biasa) supaya
image punya manifest untuk kedua arsitektur sekaligus — server cloud (amd64) maupun collaborator yang
pull ke Mac Apple Silicon (arm64) sama-sama bisa `docker pull` tanpa error
`no matching manifest for linux/arm64/v8`. Builder default docker Desktop gak bisa multi-platform +
push; kalau builder aktif kamu masih driver `docker` biasa, buat dulu builder baru:
```bash
docker buildx create --name multiarch --driver docker-container --use
```
```bash
docker buildx build --platform linux/amd64,linux/arm64 -t iassprism/prism-app:latest --push .

# transfer file pendukung ke server
scp docker-compose.prod.yml Caddyfile .env.production user@prism.iassupport.id:~/prism/
scp docker/db-init/01-prism.sql user@prism.iassupport.id:~/prism/docker/db-init/01-prism.sql
rsync -avz docfile/ user@prism.iassupport.id:~/prism/docfile/
```

## Langkah di server

```bash
cd ~/prism

# 1. (Kalau image private) login ke Docker Hub
docker login -u iassprism

# 2. Isi DB_PASSWORD di .env.production dengan password kuat
nano .env.production   # ganti CHANGE_ME_STRONG_PASSWORD

# 3. Isi email Let's Encrypt di Caddyfile
nano Caddyfile

# 4. Pull image & jalankan stack production
#    PENTING: pakai --env-file .env.production supaya ${DB_PASSWORD} di compose
#    ikut ter-substitusi (docker compose defaultnya cuma baca file ".env")
docker compose -f docker-compose.prod.yml --env-file .env.production pull
docker compose -f docker-compose.prod.yml --env-file .env.production up -d

# 5. Cek log sampai migration & Caddy issue sertifikat sukses
docker compose -f docker-compose.prod.yml logs -f
```

Setelah container `db` sehat, MySQL otomatis import `docker/db-init/01-prism.sql`
(hanya saat volume `db_data` masih kosong). Container `app` menjalankan migration
(`entrypoint.sh`) lalu Caddy otomatis minta sertifikat HTTPS untuk `prism.iassupport.id`
begitu DNS sudah mengarah ke server dan port 80/443 bisa diakses dari luar.

## Verifikasi
- `https://prism.iassupport.id` bisa diakses dan sertifikat valid (bukan self-signed).
- Login berhasil, data project/tiket sesuai dump terakhir.
- Buka salah satu dokumen project (menu Monitoring) — memverifikasi `docfile/` ter-mount benar.

## Update selanjutnya (setelah live)
```bash
# di PC lokal: build ulang & push versi baru
docker buildx build --platform linux/amd64,linux/arm64 -t iassprism/prism-app:latest --push .

# di server: pull & restart
docker compose -f docker-compose.prod.yml --env-file .env.production pull
docker compose -f docker-compose.prod.yml --env-file .env.production up -d
```
Data di `db_data`, `storage_data`, `caddy_data` (sertifikat) tetap ada selama volume tidak dihapus.

## Kalau mau regenerate dump database (data berubah signifikan sebelum migrasi ulang)
```bash
mysqldump -u root --default-character-set=utf8mb4 prism > docker/db-init/01-prism.sql
```
