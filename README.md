# Backend API WargaRT

Backend Laravel 13 / PHP 8.3+ untuk Desktop Admin dan Android Warga. Backend adalah satu-satunya komponen yang mengakses MySQL; Desktop dan Android wajib lewat REST API v1.

## Fitur utama

- Registrasi dan login dua langkah dengan OTP.
- Password hashing lewat Laravel Hash.
- Access token JWT HS256 berumur pendek.
- Refresh token opaque 256-bit, disimpan sebagai SHA-256 hash dan dirotasi setiap refresh.
- Role `ADMIN` dan `CITIZEN` di middleware server.
- Profil, keluarga, pencarian warga, berita, surat, iuran, notifikasi, media/upload, live chat, presence, dan audit log.
- OpenAPI di `docs/api/openapi.yaml`; Swagger UI tersedia pada `/docs`.
- Railway-ready via `Dockerfile` dan `railway.json`.

## Menjalankan lokal

Prasyarat: PHP 8.3+, Composer, MySQL 8.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Endpoint penting:

- API base LAN: `http://192.168.0.105:8000/api/v1`
- Swagger UI LAN: `http://192.168.0.105:8000/docs`
- OpenAPI YAML LAN: `http://192.168.0.105:8000/openapi.yaml`
- Readiness LAN: `http://192.168.0.105:8000/api/v1/health/ready`

Jika IP laptop berubah, cek dengan `ipconfig`, lalu pakai IPv4 aktif laptop.

## Membuat admin pertama

Jangan membuat endpoint publik untuk admin pertama. Gunakan console:

```bash
php artisan wargart:create-admin admin.nama +6281234567890
```

Command akan meminta password secara interaktif dan menyimpan hash password.

## Data demo

Data demo hanya untuk local/testing:

```dotenv
SEED_DEMO_DATA=true
DEMO_ADMIN_PASSWORD=AdminDemo@12345
DEMO_CITIZEN_PASSWORD=rio123
OTP_FIXED_CODE=123456
```

```bash
php artisan db:seed
```

Seeder menolak berjalan di production.

Akun demo admin yang dibuat:

- Username: `admin.demo`
- Password: `AdminDemo@12345`
- OTP local/testing: `123456`

Akun demo warga yang dibuat:

- Username: `rio`
- Password: `rio123`
- OTP local/testing: `123456`

## Test dan validasi

```bash
php artisan route:list --path=api
php artisan migrate:fresh --seed --force
php artisan test
```

Validasi Tahap 7 di environment Codex:

- `route:list --path=api`: lulus, 58 route.
- `migrate:fresh --seed --force`: lulus.
- `php artisan test`: lulus, 9 test, 8 passed, 1 skipped, 62 assertion.

## Deploy ke Railway

Project ini sudah menyiapkan:

- `Dockerfile`: install dependency PHP, copy vendor dari Composer image, menunggu MySQL siap, migrate saat start, lalu bind ke `$PORT`.
- `railway.json`: memakai Dockerfile, healthcheck ke `/api/v1/health/ready`, restart on failure.
- `.env.example`: daftar variabel untuk Railway.

Langkah deployment yang dilakukan oleh pemilik project:

1. Buat project Railway dan service Backend dari folder `backend`.
2. Tambahkan Railway MySQL atau MySQL eksternal.
3. Isi environment variables sesuai tabel di bawah.
4. Deploy service Backend.
5. Buka `/api/v1/health/ready`; response sukses berarti aplikasi dan database terhubung.
6. Buat admin pertama lewat Railway shell:

   ```bash
   php artisan wargart:create-admin admin.nama +6281234567890
   ```

## Environment Variables Railway

| Variable | Wajib | Contoh | Keterangan |
|---|---:|---|---|
| `APP_NAME` | Ya | `WargaRT` | Nama aplikasi. |
| `APP_ENV` | Ya | `production` | Pakai `production` di Railway. |
| `APP_KEY` | Ya | `base64:...` | Generate dengan `php artisan key:generate --show`. |
| `APP_DEBUG` | Ya | `false` | Jangan `true` di production. |
| `APP_URL` | Ya | `https://nama-service.up.railway.app` | URL publik Backend. |
| `APP_TIMEZONE` | Tidak | `Asia/Jakarta` | Zona waktu aplikasi. |
| `LOG_LEVEL` | Tidak | `info` | Level log Laravel. |
| `JWT_SECRET` | Ya | string acak 32+ karakter | Secret JWT terpisah dari `APP_KEY`. |
| `DB_CONNECTION` | Ya | `mysql` | Database driver. |
| `DB_HOST` | Ya | dari Railway MySQL | Host MySQL. |
| `DB_PORT` | Ya | `3306` | Port MySQL. |
| `DB_DATABASE` | Ya | dari Railway MySQL | Nama database. |
| `DB_USERNAME` | Ya | dari Railway MySQL | User database. |
| `DB_PASSWORD` | Ya | dari Railway MySQL | Password database. |
| `AUTH_ACCESS_TTL_MINUTES` | Tidak | `15` | Umur access token. |
| `AUTH_REFRESH_TTL_DAYS` | Tidak | `30` | Umur refresh token. |
| `OTP_TTL_SECONDS` | Tidak | `300` | Masa berlaku OTP. |
| `OTP_RESEND_COOLDOWN_SECONDS` | Tidak | `60` | Jeda resend OTP. |
| `OTP_MAX_ATTEMPTS` | Tidak | `5` | Maksimal percobaan OTP. |
| `OTP_PROVIDER` | Ya | `log` | Untuk uji privat boleh `log`; untuk go-live gunakan adapter OTP produksi. |
| `OTP_LOG_ALLOW_PRODUCTION` | Kondisional | `true` saat uji privat | Default `false`. Aktifkan hanya sementara jika Railway production masih memakai `OTP_PROVIDER=log`. |
| `FILESYSTEM_DISK` | Tidak | `local` | Penyimpanan upload. |
| `APP_STORAGE_ROOT` | Tidak | `/data` | Root storage di container. |
| `QUEUE_CONNECTION` | Tidak | `database` | Queue driver. |
| `PORT` | Tidak | otomatis Railway | Railway biasanya mengisi ini otomatis. |
| `SEED_DEMO_DATA` | Tidak | `false` | Jangan aktifkan di production. |
| `DEMO_ADMIN_PASSWORD` | Tidak | kosong | Hanya untuk seeding non-production. |
| `DEMO_ADMIN_PHONE` | Tidak | `+6281234500099` | Hanya data demo. |
| `DEMO_CITIZEN_PASSWORD` | Tidak | kosong | Hanya untuk seeding non-production. |

Catatan OTP: project ini tidak bergantung pada WAHA lama. Provider `log` cocok untuk uji privat karena kode OTP bisa dilihat dari log aplikasi. Untuk go-live publik, pasang adapter OTP milik Anda sendiri tanpa mengubah kontrak API.
