# File & Video Compression Backend (Laravel)

Ini adalah *backend* untuk aplikasi kompresi audio & video berbasis **Laravel 12**, **PostgreSQL**, dan **Redis**. Semua kompresi media berat dilakukan secara *asynchronous* (di background) menggunakan integrasi **FFmpeg**.

## 🛠️ Prasyarat / Requirements
Sebelum menjalankan project ini, pastikan sistem komputermu sudah memiliki:
1. **PHP >= 8.2** (pastikan ekstensi `pdo_pgsql`, `redis`, dll aktif).
2. **Composer** (untuk instalasi dependensi Laravel).
3. **PostgreSQL** (sebagai database utama).
4. **Redis** (sebagai antrean / *queue worker*). Memerlukan instalasi via instalasi lokal, brew, atau Docker.
5. **FFmpeg & FFprobe** (harus terinstal di *system environment* PATH sehingga OS mengenali perintah `ffmpeg`).

## ⚙️ Cara Instalasi & Menjalankan

### 1. Klon & Install Dependensi
Buka terminal dan arahkan ke folder `compression-web-backend`, lalu install package:
```bash
cd compression-web-backend
composer install
```

### 2. Konfigurasi Environment (`.env`)
Salin file `.env.example` menjadi `.env` (jika `.env` belum ada):
```bash
cp .env.example .env
```
Lalu pastikan pengaturan database dan queue mengarah ke konfigurasi yang benar:
```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=compression_web
DB_USERNAME=postgres
DB_PASSWORD=root  # Sesuaikan dengan password pgsql kamu

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

FRONTEND_URL=http://localhost:5173
```

### 3. Konfigurasi Khusus Limit File Upload (`php.ini`)
Secara khusus, karena kita membolehkan upload video hingga 512MB, kamu HARUS memastikan batas upload dari PHP (di dalam file `php.ini` sistem-mu) sudah ditingkatkan.
Buka `php.ini` (lokasinya bisa dicek via perintah `php --ini`), lalu ubah baris ini:
```ini
upload_max_filesize = 512M
post_max_size = 512M
```
*(Ingat untuk me-restart terminal atau service php setelah merubahnya)*.

### 4. Setup Database & Storage
Jalankan migrasi tabel ke PostgreSQL dan buat *symlink* folder storage agar file bisa diakses secara publik (dimainkan via web):
```bash
php artisan migrate
php artisan storage:link
```

### 5. Jalankan Server
Kini kamu perlu membuka **DUA (2)** tab terminal yang berbeda untuk menjalankan sistem backend ini.

**Terminal 1 (REST API Server):**
```bash
php artisan serve
```
*API akan berjalan di `http://localhost:8000`.*

**Terminal 2 (Queue Worker FFMPEG):**
```bash
php artisan queue:work --queue=default
```
*(Jangan pernah menutup Terminal 2 ini! Terminal inilah yang secara diam-diam memproses FFMPEG di background saat ada request kompresi baru).*

---

## 🔒 Otentikasi
Aplikasi ini menggunakan **Laravel Sanctum**. Token akses disimpan menggunakan standar *Bearer Token* dan wajib dikirimkan di *header* `Authorization: Bearer <token>` pada setiap request dari Frontend.
