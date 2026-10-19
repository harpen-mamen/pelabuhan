# Matriks tugas dan akses

Backend menegakkan matriks ini pada API. Filament web hanya menerima `super_admin`; role operasional memakai Flutter. Akun nonaktif ditolak middleware.

| Role | Tugas utama | Akses data dan tindakan |
|---|---|---|
| `super_admin` | Admin utama dan pemilik sistem | Panel Filament web, seluruh modul, akun dan role, audit, serta akses darurat API penuh. |
| `admin` | Administrasi operasional melalui Flutter | Kelola pengguna non-`super_admin`, master data, booking, voyage, manifest, tarif, laporan, dashboard, dan audit. Tidak bisa masuk Filament, mengubah akun `super_admin`, atau memutuskan verifikasi UPP. |
| `operator` | Operasi kapal dan muatan | Buat/perbarui booking dan statusnya, siapkan/kirim manifest, buat/perbarui voyage dan status keberangkatan, baca kapal/pelanggan/perusahaan/tarif, serta lihat laporan operasional. Tidak dapat memverifikasi manifest atau menghapus data. |
| `upp` | Pengawasan dan verifikasi | Baca manifest, setujui/tolak/minta revisi, baca realisasi dan selisih, laporan kepatuhan/PNBP, audit, serta data kapal/voyage yang diperlukan. Tidak membuat booking, manifest, atau realisasi. |
| `checker` | Pencatatan di dermaga | Baca manifest/voyage/kapal yang diperlukan, catat realisasi muat dan bongkar, lihat statistik checker. Tidak mengubah booking atau hasil verifikasi UPP. |
| `pelanggan` | Pengiriman milik sendiri | Registrasi mandiri, lihat profil sendiri, tarif dan voyage, buat booking, lihat/tracking booking sendiri, dan membaca notifikasinya sendiri. Tidak bisa melihat booking pelanggan lain. |
| `manajer` | Pemantauan kinerja | Baca dashboard dan data operasional, laporan arus barang/manifest/kepatuhan/PNBP, tren bulanan, serta audit. Tidak mengubah data transaksi. |
| `kantor_perhubungan` | Pengawasan lintas instansi | Baca ringkasan dan data laporan, manifest, booking, voyage, kapal, tren, PNBP/kepatuhan, serta audit. Tidak mengubah data operasional. |

## Alur kerja utama

1. Pelanggan mendaftar di aplikasi dan membuat booking. Akun staf tidak dapat dibuat lewat registrasi publik.
2. Operator memproses booking, membuat voyage, memilih barang booking ke manifest, lalu mengirim manifest untuk verifikasi UPP.
3. UPP menyetujui, menolak dengan alasan, atau meminta revisi. Checker hanya dapat mencatat muat setelah manifest disetujui; seluruh detail muatan harus tercatat sebelum voyage berangkat.
4. Operator menandai voyage berangkat dan tiba. Setelah kapal tiba, checker mencatat bongkar sampai seluruh detail selesai. Selisih disimpan sebagai realisasi bermasalah, tetap muncul pada laporan meski status manifest bergerak ke tahap berikutnya, dan memicu notifikasi.
5. Manajer dan kantor perhubungan membaca ringkasan/laporan; admin utama mengelola akses melalui Filament.

## Penerapan izin

- `PermissionMiddleware` memeriksa izin per tindakan (`resource.read`, `resource.create`, `resource.update`, `resource.delete`, atau izin alur khusus) pada setiap route API.
- Keputusan verifikasi manifest dibatasi ke UPP dan admin utama; admin operasional tidak memiliki izin verifikasi.
- Aplikasi mobile menyediakan tindakan status booking, verifikasi manifest, pencatatan muat/bongkar, dan status keberangkatan/kedatangan voyage sesuai role.
- Controller tetap memvalidasi kepemilikan booking pelanggan dan membatasi pengelolaan akun `super_admin`.
- `role:super_admin` mempunyai wildcard API, tetapi akses panel web tetap memerlukan akun aktif.
- Perubahan role staf harus dilakukan oleh admin utama; registrasi publik hanya menerima akun `pelanggan`.
