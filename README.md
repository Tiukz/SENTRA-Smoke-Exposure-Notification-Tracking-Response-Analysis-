# SENTRA

**Smoke Exposure Notification, Tracking, Response & Analysis**

SENTRA adalah prototipe decision-support berbasis Laravel untuk memproyeksikan lintasan asap dari hotspot kebakaran, memperkirakan fasilitas yang berpotensi terdampak, menentukan jendela intervensi, dan membandingkan alternatif rute berdasarkan overlap dengan koridor proyeksi asap.

> Model SENTRA menggunakan proyeksi geometris sederhana. Hasilnya bukan prakiraan dispersi atmosfer resmi dan bukan ukuran dosis paparan medis.

## Fitur utama

- Snapshot Riil offline: arsip hotspot NASA FIRMS, cuaca, dan fasilitas OpenStreetMap.
- Data Aktual: NASA FIRMS, Open-Meteo, dan OpenStreetMap/Overpass.
- Mode Simulasi dan What-if arah angin, kecepatan angin, serta horizon proyeksi.
- Ringkasan kondisi seluruh hotspot representatif.
- Analisis risiko, urgensi, ETA, Intervention Window, dan prioritas respons.
- Perbandingan rute berbasis OSRM dan overlap koridor asap.
- Dashboard Blade, JavaScript vanilla, dan Leaflet.

## Kebutuhan

- PHP 8.3+
- Composer
- MySQL
- Node.js dan npm

## Menjalankan aplikasi

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Buat database MySQL bernama `sentra`, sesuaikan koneksi pada `.env`, lalu jalankan:

```bash
php artisan migrate
npm install
npm run build
php artisan serve
```

Buka `http://127.0.0.1:8000/sentra`.

Snapshot Riil dapat dijalankan tanpa API key dan tanpa koneksi ke penyedia data eksternal.

## Data Aktual NASA FIRMS

Isi key pada `.env`:

```env
FIRMS_MAP_KEY=your_firms_map_key
```

Key hanya digunakan oleh backend dan tidak dikirimkan ke browser. Open-Meteo, Overpass, dan OSRM tidak memerlukan API key.

Untuk memperbarui snapshot offline dari seluruh provider live:

```bash
php artisan sentra:snapshot-refresh
```

Snapshot hanya ditimpa apabila hotspot, cuaca, dan fasilitas semuanya valid.

## Pengujian

```bash
php artisan test
vendor/bin/pint --test
```

## Data dan atribusi

- Hotspot: NASA FIRMS
- Cuaca: Open-Meteo
- Fasilitas dan peta: OpenStreetMap contributors
- Routing: OSRM

Data arsip untuk demo offline tersimpan di `storage/app/datasets/real-snapshot.json`.
