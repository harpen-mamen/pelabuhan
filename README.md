# SIMuatanTahuna

Sistem pengelolaan muatan Pelabuhan Tahuna dengan Laravel 12, Filament, Flutter, dan MySQL.

## Struktur aplikasi

- `backend/` — Laravel REST API dan panel web Filament.
- `mobile/` — aplikasi Flutter untuk admin operasional, petugas, manajer, dan pelanggan.
- `rancangan.md` — rancangan sistem.

Panel web `/admin` hanya dapat digunakan oleh akun aktif dengan role `super_admin`. Akun admin operasional dan pengguna lain memakai aplikasi mobile. Registrasi dari aplikasi hanya membuat akun pelanggan; akun staf dibuat oleh admin utama.

## Laravel dan MySQL

1. Siapkan PHP 8.3+, Composer, dan MySQL. Pastikan folder PHP 8.3 berada lebih awal pada Windows `PATH` daripada PHP lama (misalnya XAMPP), lalu buka terminal baru. Folder PHP 8.3 pada komputer ini: `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe`.
2. Pastikan database `pelabuhan` tersedia. Atur koneksi lokal di `backend/.env` tanpa membagikan kredensial.
3. Jalankan migrasi dari `backend/` menggunakan PHP 8.3:

   ```sh
   php artisan migrate
   ```

   Migrasi akan menaikkan akun `admin` yang masih ber-role `admin` menjadi `super_admin`, agar akun admin utama tetap bisa masuk ke panel web.
4. Saat menyiapkan folder proyek pertama kali, dari `backend/` pasang dependency frontend dan build aset Tailwind Filament:

   ```bat
   npm.cmd install
   npm.cmd run build
   ```

   Hasil build dashboard disimpan di `backend/public/build`, sehingga server Laravel dapat menyajikannya.
5. Dari folder `backend/`, verifikasi versi lalu jalankan:

   ```sh
   php -v
   php artisan serve
   ```

   `php -v` harus menunjukkan PHP 8.3 atau lebih baru. Jika terminal masih memakai PHP lama, tambahkan folder PHP 8.3 di atas ke `PATH` Windows sebelum folder PHP lama, kemudian buka terminal baru. Untuk sementara, atur PATH hanya pada jendela CMD saat ini:

   ```bat
   set "PATH=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;%PATH%"
   php -v
   php artisan serve
   ```
6. Buka `http://localhost:8000/admin` untuk panel admin utama. Untuk instalasi baru, jalankan seeder setelah migrasi bila data demo diperlukan.

## Flutter

Pasang Flutter SDK, lalu dari `mobile/` jalankan:

```sh
flutter create --platforms=android,ios .
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

`10.0.2.2` adalah alamat komputer host dari Android emulator. Untuk perangkat fisik, ganti dengan alamat IP LAN komputer. Aplikasi menyimpan token JWT di penyimpanan aman perangkat dan menampilkan menu berdasarkan role akun.

Untuk rilis, gunakan HTTPS dan URL API produksi. Detail langkah mobile ada di [mobile/README.md](mobile/README.md).
