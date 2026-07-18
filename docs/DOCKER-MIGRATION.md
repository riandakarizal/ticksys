# Migrasi PRISM ke PC Lain (Docker)

## Yang dibawa
Copy seluruh folder `prism/` (termasuk folder `docker/`, `.env`, dan `docker/db-init/01-prism.sql`)
ke PC tujuan. **Jangan** copy `vendor/`, `node_modules/`, `public/build` — semua di-build ulang oleh Docker.

`docker/db-init/01-prism.sql` adalah dump database saat ini (hasil `mysqldump`, terakhir di-regenerate
2026-07-07 — sudah termasuk tabel `pjct_doc`). Saat container `db` pertama kali dibuat, MySQL otomatis
import file ini — database langsung terisi data yang sama. Sudah dites import bersih ke image `mysql:8.0`
yang dipakai `docker-compose.yml`.

> **Ingat regenerate lagi** kalau ada perubahan data/schema signifikan setelah tanggal di atas:
> `mysqldump -u root --default-character-set=utf8mb4 prism > docker/db-init/01-prism.sql`

## Yang dibutuhkan di PC tujuan
- Docker Desktop (Windows/Mac/Linux)

## Cara jalanin
```bash
cd prism
docker compose up --build -d
```

Ini akan:
1. Build image app (composer install + npm build + PHP runtime)
2. Start MySQL, import `01-prism.sql` (hanya saat volume `db_data` masih kosong/baru)
3. Start app, jalankan migration (aman diulang), `storage:link`, cache config
4. App tersedia di `http://localhost:8080`

## Konfigurasi
`.env` dipakai apa adanya — `DB_HOST`/`DB_PORT` di-override otomatis ke `db`/`3306` oleh
`docker-compose.yml`. Kalau perlu expose ke jaringan lokal PC baru, port `8080` sudah di-bind ke
`0.0.0.0` secara default di dalam container; tinggal cek Windows Firewall di PC tersebut.

## Update setelah ada perubahan data/kode
```bash
docker compose up --build -d   # rebuild image setelah pull kode baru
docker compose down            # stop (data db tetap ada di volume db_data)
docker compose down -v         # stop + hapus volume (data db ikut hilang)
```

## Kalau mau import ulang dump manual (bukan first-boot)
```bash
docker compose exec -T db mysql -uroot prism < docker/db-init/01-prism.sql
```
