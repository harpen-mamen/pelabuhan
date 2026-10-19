<?php

namespace Database\Seeders;

use App\Models\Kapal;
use App\Models\Perusahaan;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Users dengan berbagai role
        User::create([
            "nama" => "Admin Sistem",
            "username" => "admin",
            "email" => "admin@simuatan-tahuna.test",
            "password" => Hash::make("password123"),
            "role" => "super_admin",
            "kontak" => "081234567890",
            "is_active" => true,
        ]);

        User::create([
            "nama" => "Budi Operator",
            "username" => "operator",
            "email" => "operator@simuatan-tahuna.test",
            "password" => Hash::make("password123"),
            "role" => "operator",
            "kontak" => "081234567891",
            "is_active" => true,
        ]);

        User::create([
            "nama" => "Andi Checker",
            "username" => "checker",
            "email" => "checker@simuatan-tahuna.test",
            "password" => Hash::make("password123"),
            "role" => "checker",
            "kontak" => "081234567892",
            "is_active" => true,
        ]);

        User::create([
            "nama" => "Sari Manajer",
            "username" => "manajer",
            "email" => "manajer@simuatan-tahuna.test",
            "password" => Hash::make("password123"),
            "role" => "manajer",
            "kontak" => "081234567893",
            "is_active" => true,
        ]);

        User::create([
            "nama" => "Pak Syahbandar",
            "username" => "upp",
            "email" => "upp@simuatan-tahuna.test",
            "password" => Hash::make("password123"),
            "role" => "upp",
            "kontak" => "081234567894",
            "is_active" => true,
        ]);

        // Pelanggan + user terkait
        $pelangganUser = User::create([
            "nama" => "Pak Pelanggan",
            "username" => "pelanggan",
            "email" => "pelanggan@simuatan-tahuna.test",
            "password" => Hash::make("password123"),
            "role" => "pelanggan",
            "kontak" => "081234567895",
            "is_active" => true,
        ]);

        // Kapal
        Kapal::create(["nama_kapal" => "KM Sangihe Raya", "kapasitas_dwt" => 5000, "jenis_kapal" => "Tol Laut", "pemilik" => "PT Pelni"]);
        Kapal::create(["nama_kapal" => "KM Bahari Nusantara", "kapasitas_dwt" => 3500, "jenis_kapal" => "Kargo", "pemilik" => "PT Dharma Lautan"]);
        Kapal::create(["nama_kapal" => "KM Maritim Indah", "kapasitas_dwt" => 2500, "jenis_kapal" => "Kargo Umum", "pemilik" => "PT Tanto"]);

        // Perusahaan
        $perusahaan1 = Perusahaan::create([
            "nama_perusahaan" => "PT Sejahtera Mandiri",
            "alamat" => "Jl. Pelabuhan No. 1, Tahuna",
            "npwp" => "01.234.567.8-901.000",
            "kontak" => "082345678901",
        ]);

        $perusahaan2 = Perusahaan::create([
            "nama_perusahaan" => "CV Laut Biru",
            "alamat" => "Jl. Sulawesi No. 25, Manado",
            "npwp" => "02.345.678.9-012.000",
            "kontak" => "082345678902",
        ]);

        // Pelanggan record
        \App\Models\Pelanggan::create([
            "id_user" => $pelangganUser->id_user,
            "id_perusahaan" => $perusahaan1->id_perusahaan,
            "nama" => "Pak Pelanggan",
            "alamat" => "Tahuna",
            "kontak" => "081234567895",
        ]);

        // ====================================================================
        // TARIF DEFAULT - Sesuai PMK/Kemenhub untuk Tol Laut
        // ====================================================================
        $tarifs = [
            // Umum - semua rute
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "umum", "rute" => "Surabaya-Tahuna", "tarif_per_kg" => 500, "tarif_per_m3" => 1000],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "umum", "rute" => "Makassar-Tahuna", "tarif_per_kg" => 450, "tarif_per_m3" => 900],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "umum", "rute" => "Tahuna-Surabaya", "tarif_per_kg" => 550, "tarif_per_m3" => 1100],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "umum", "rute" => "Tahuna-Makassar", "tarif_per_kg" => 480, "tarif_per_m3" => 950],

            // Bahan Pokok (disubsidi)
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "bahan_pokok", "rute" => "Surabaya-Tahuna", "tarif_per_kg" => 250, "tarif_per_m3" => 500],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "bahan_pokok", "rute" => "Makassar-Tahuna", "tarif_per_kg" => 200, "tarif_per_m3" => 400],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "bahan_pokok", "rute" => "Tahuna-Surabaya", "tarif_per_kg" => 300, "tarif_per_m3" => 600],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "bahan_pokok", "rute" => "Tahuna-Makassar", "tarif_per_kg" => 220, "tarif_per_m3" => 450],

            // Berbahaya (lebih mahal)
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "berbahaya", "rute" => "Surabaya-Tahuna", "tarif_per_kg" => 1000, "tarif_per_m3" => 2000],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "berbahaya", "rute" => "Makassar-Tahuna", "tarif_per_kg" => 900, "tarif_per_m3" => 1800],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "berbahaya", "rute" => "Tahuna-Surabaya", "tarif_per_kg" => 1100, "tarif_per_m3" => 2200],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "berbahaya", "rute" => "Tahuna-Makassar", "tarif_per_kg" => 950, "tarif_per_m3" => 1900],

            // B3 (paling mahal)
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "b3", "rute" => "Surabaya-Tahuna", "tarif_per_kg" => 1500, "tarif_per_m3" => 3000],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "b3", "rute" => "Makassar-Tahuna", "tarif_per_kg" => 1300, "tarif_per_m3" => 2600],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "b3", "rute" => "Tahuna-Surabaya", "tarif_per_kg" => 1600, "tarif_per_m3" => 3200],
            ["jenis_layanan" => "Bongkar Muat", "kategori_barang" => "b3", "rute" => "Tahuna-Makassar", "tarif_per_kg" => 1400, "tarif_per_m3" => 2800],
        ];

        foreach ($tarifs as $t) {
            Tarif::create([
                "jenis_layanan" => $t["jenis_layanan"],
                "kategori_barang" => $t["kategori_barang"],
                "rute" => $t["rute"],
                "tarif_per_kg" => $t["tarif_per_kg"],
                "tarif_per_m3" => $t["tarif_per_m3"],
                "berlaku_dari" => "2026-01-01",
                "berlaku_hingga" => null,
            ]);
        }
    }
}
