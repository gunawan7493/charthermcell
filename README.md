# CharThermCell

Dashboard monitoring TEG biochar tempurung kelapa (SMAN 1 Blitar) — versi Laravel
dari aplikasi satu file `charthermcell.php`.

Aplikasi menampilkan suhu sisi panas/dingin, delta T, tegangan, arus, dan daya
keluaran dari perangkat CharThermCell-01, dengan data diambil dari Blynk Cloud
melalui proxy di sisi server.

## Fitur

- Dashboard responsif (gauge, kartu metrik, grafik 5 menit terakhir).
- Token Blynk hanya ada di server; browser tidak pernah menerima token.
- API internal: `GET /api/telemetry`, `POST /api/controls/buzzer`,
  `POST /api/controls/calibrate`.
- Rate limit: 120 pembacaan/menit dan 10 perintah/menit per IP.
- Snapshot Blynk di-cache 1 detik agar banyak pengunjung tidak membanjiri Blynk.
- Pembacaan pin digabung dalam satu request (`get?V0&V1&…`).

## Kebutuhan

- PHP 8.3+ dengan ekstensi `curl`, `mbstring`, `openssl`, `pdo_sqlite`
- Composer 2
- Node.js 20+ (hanya untuk membangun aset)

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate
```

Isi `BLYNK_TOKEN` di `.env` dengan auth token Blynk (jangan commit).

## Konfigurasi

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `BLYNK_TOKEN` | — | Auth token Blynk Cloud (wajib) |
| `BLYNK_BASE_URL` | `https://blynk.cloud/external/api` | Base URL API Blynk |
| `BLYNK_TIMEOUT` | `8` | Timeout request ke Blynk (detik) |
| `BLYNK_CACHE_SECONDS` | `1` | Lama cache snapshot telemetry |
| `BLYNK_DEVICE_NAME` | `CharThermCell-01` | Nama perangkat di footer |
| `BLYNK_CONTROLS_ENABLED` | `true` | Aktifkan tombol kontrol (butuh datastream V7/V8) |

Pemetaan datastream ada di `config/blynk.php` (V0–V6 untuk pembacaan, V7/V8 untuk
kontrol). Hanya pin di `blynk.controls` yang bisa ditulis.

## Menjalankan lokal

```bash
php artisan serve
```

## Test

```bash
composer test
```

Test tidak memanggil Blynk sungguhan (memakai `Http::fake`). `composer test`
membersihkan config cache lebih dulu supaya hasil tes tidak terpengaruh
konfigurasi produksi.

## Deployment

Contoh konfigurasi Caddy + PHP-FPM:

```caddy
demo-app.example.com {
    root * /path/to/charthermcell/public
    php_fastcgi unix//path/to/php-fpm.sock
    file_server
}
```

Jalankan `php artisan optimize` setelah mengubah konfigurasi produksi.
