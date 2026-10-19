<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Booking;
use App\Models\Manifest;
use App\Models\Pelanggan;
use App\Models\RealisasiBongkarMuat;
use App\Models\User;
use App\Models\Voyage;
use App\Models\Kapal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * ========================================================================
     * DASHBOARD BERDASARKAN ROLE
     * ========================================================================
     * - admin: Full access, semua statistik
     * - operator: Booking, manifest yang dibuatnya
     * - upp: Verifikasi manifest
     * - checker: Realisasi bongkar/muat
     * - pelanggan: Booking miliknya sendiri
     * - manajer: Overview semua data
     * ========================================================================
     */

    public function summary(Request $request)
    {
        $user = $request->user();

        $data = match($user->role) {
            'admin', 'super_admin' => $this->adminSummary(),
            'operator' => $this->operatorSummary($user),
            'upp' => $this->uppSummary(),
            'checker' => $this->checkerSummary(),
            'pelanggan' => $this->pelangganSummary($user),
            'manajer', 'kantor_perhubungan' => $this->manajerSummary(),
            default => ["message" => "Dashboard untuk role ini belum tersedia"],
        };

        return response()->json(["status" => "success", "data" => $data]);
    }

    private function adminSummary(): array
    {
        return [
            // Overview
            "total_booking" => Booking::count(),
            "total_manifest" => Manifest::count(),
            "total_user" => User::count(),
            "total_pelanggan" => Pelanggan::count(),
            "total_voyage" => Voyage::count(),
            "total_kapal" => Kapal::count(),

            // Booking Stats
            "booking_menunggu" => Booking::where("status", "menunggu")->count(),
            "booking_diproses" => Booking::where("status", "diproses")->count(),
            "booking_dimuat" => Booking::whereIn("status", ["siap_dimuat", "dimuat", "dalam_perjalanan"])->count(),
            "booking_selesai" => Booking::where("status", "diterima")->count(),
            "booking_ditolak" => Booking::where("status", "ditolak")->count(),
            "booking_dibatalkan" => Booking::where("status", "dibatalkan")->count(),

            // Booking Hari Ini
            "booking_hari_ini" => Booking::whereDate("created_at", today())->count(),

            // Manifest Stats
            "manifest_menunggu_verif" => Manifest::where("status", "menunggu_verifikasi")->count(),
            "manifest_disetujui" => Manifest::where("status", "disetujui")->count(),
            "manifest_ditolak" => Manifest::where("status", "ditolak")->count(),
            "manifest_perlu_klarifikasi" => Manifest::where("status", "perlu_klarifikasi")->count(),
            "manifest_selesai" => Manifest::where("status", "selesai")->count(),
            "manifest_hari_ini" => Manifest::whereDate("tgl_manifest", today())->count(),

            // Realisasi Stats
            "realisasi_hari_ini" => RealisasiBongkarMuat::whereDate("waktu_input", today())->count(),
            "total_muat" => RealisasiBongkarMuat::where("jenis", "muat")->count(),
            "total_bongkar" => RealisasiBongkarMuat::where("jenis", "bongkar")->count(),

            // Kategori Barang
            "kategori_barang" => Barang::selectRaw("kategori, COUNT(*) as total")
                ->groupBy("kategori")
                ->orderBy("total", "desc")
                ->get(),

            // Top Pelanggan
            "top_pelanggan" => Booking::selectRaw("id_pelanggan, COUNT(*) as total_booking")
                ->with("pelanggan:id_pelanggan,nama")
                ->groupBy("id_pelanggan")
                ->orderBy("total_booking", "desc")
                ->limit(5)
                ->get(),

            // Kapal Aktif
            "kapal_aktif" => Voyage::where("status", "aktif")->with("kapal")->count(),

            // User Aktif
            "user_aktif" => User::where("is_active", true)->count(),
            "user_per_role" => User::selectRaw("role, COUNT(*) as total")
                ->groupBy("role")
                ->get(),
        ];
    }

    private function operatorSummary($user): array
    {

        return [
            // Booking untuk operator
            "booking_menunggu" => Booking::where("status", "menunggu")->count(),
            "booking_diproses" => Booking::where("status", "diproses")->count(),
            "booking_manifest" => Booking::where("status", "manifest")->count(),
            "booking_selesai" => Booking::where("status", "diterima")->count(),
            "booking_hari_ini" => Booking::whereDate("tgl_booking", today())->count(),

            // Manifest yang dibuat operator ini
            "manifest_saya" => Manifest::where("id_user_pembuat", $user->id_user)->count(),
            "manifest_menunggu" => Manifest::where("id_user_pembuat", $user->id_user)
                ->where("status", "menunggu_verifikasi")->count(),
            "manifest_disetujui" => Manifest::where("id_user_pembuat", $user->id_user)
                ->where("status", "disetujui")->count(),

            // Overview
            "manifest_menunggu_verif" => Manifest::where("status", "menunggu_verifikasi")->count(),
            "perlu_klarifikasi" => Manifest::where("status", "perlu_klarifikasi")->count(),
            "perlu_action" => 
                Booking::where("status", "menunggu")->count() +
                Manifest::where("status", "perlu_klarifikasi")->count(),
        ];
    }

    private function uppSummary(): array
    {
        return [
            // Verifikasi Stats
            "menunggu_verifikasi" => Manifest::where("status", "menunggu_verifikasi")->count(),
            "perlu_klarifikasi" => Manifest::where("status", "perlu_klarifikasi")->count(),
            
            // Today's Stats
            "menunggu_hari_ini" => Manifest::where("status", "menunggu_verifikasi")
                ->whereDate("created_at", today())->count(),
            "disetujui_hari_ini" => Manifest::where("status", "disetujui")
                ->whereDate("updated_at", today())->count(),
            "ditolak_hari_ini" => Manifest::where("status", "ditolak")
                ->whereDate("updated_at", today())->count(),

            // Monthly Stats
            "disetujui_bulan_ini" => Manifest::where("status", "disetujui")
                ->whereMonth("updated_at", now()->month)->count(),
            "ditolak_bulan_ini" => Manifest::where("status", "ditolak")
                ->whereMonth("updated_at", now()->month)->count(),

            // Total
            "total_disetujui" => Manifest::where("status", "disetujui")->count(),
            "total_ditolak" => Manifest::where("status", "ditolak")->count(),

            // Kategori yang menunggu verifikasi
            "kategori_menunggu" => Manifest::where("status", "menunggu_verifikasi")
                ->with("detail.barang")
                ->get()
                ->pluck("detail")
                ->flatten()
                ->groupBy("barang.kategori")
                ->map->count(),
        ];
    }

    private function checkerSummary(): array
    {
        return [
            // Manifest yang siap muat
            "manifest_siap_muat" => Manifest::where("status", "disetujui")->count(),
            "manifest_perlu_klarifikasi" => Manifest::where("status", "perlu_klarifikasi")->count(),

            // Today's Stats
            "realisasi_hari_ini" => RealisasiBongkarMuat::whereDate("waktu_input", today())->count(),
            "muat_hari_ini" => RealisasiBongkarMuat::whereDate("waktu_input", today())
                ->where("jenis", "muat")->count(),
            "bongkar_hari_ini" => RealisasiBongkarMuat::whereDate("waktu_input", today())
                ->where("jenis", "bongkar")->count(),

            // Weekly Stats
            "realisasi_minggu_ini" => RealisasiBongkarMuat::whereBetween("waktu_input", [
                now()->startOfWeek(), now()->endOfWeek()
            ])->count(),

            // Manifest dengan selisih
            "manifest_selisih" => Manifest::where("status", "perlu_klarifikasi")->count(),

            // Today's Manifest yang siap
            "manifest_aktif" => Manifest::whereIn("status", ["disetujui", "perlu_klarifikasi"])
                ->with("voyage.kapal")
                ->limit(5)
                ->get(),
        ];
    }

    private function pelangganSummary($user): array
    {
        $pelanggan = Pelanggan::where("id_user", $user->id_user)->first();
        
        if (!$pelanggan) {
            return ["message" => "Profil pelanggan tidak ditemukan"];
        }

        $query = Booking::where("id_pelanggan", $pelanggan->id_pelanggan);

        return [
            "total_booking" => (clone $query)->count(),
            "booking_menunggu" => (clone $query)->where("status", "menunggu")->count(),
            "booking_diproses" => (clone $query)->whereIn("status", ["diproses", "manifest"])->count(),
            "booking_dimuat" => (clone $query)->whereIn("status", ["siap_dimuat", "dimuat", "dalam_perjalanan"])->count(),
            "booking_selesai" => (clone $query)->where("status", "diterima")->count(),
            "booking_ditolak" => (clone $query)->where("status", "ditolak")->count(),
            "booking_dibatalkan" => (clone $query)->where("status", "dibatalkan")->count(),

            // Recent Bookings
            "booking_terbaru" => (clone $query)
                ->orderBy("created_at", "desc")
                ->limit(5)
                ->get(),

            // Info Perusahaan
            "perusahaan" => $pelanggan->perusahaan,
        ];
    }

    private function manajerSummary(): array
    {
        return [
            // Overview
            "total_booking" => Booking::count(),
            "total_manifest" => Manifest::count(),
            "total_voyage" => Voyage::count(),
            "total_kapal" => Kapal::count(),

            // Performance
            "booking_selesai_bulan_ini" => Booking::where("status", "diterima")
                ->whereMonth("updated_at", now()->month)->count(),
            "manifest_disetujui_bulan_ini" => Manifest::where("status", "disetujui")
                ->whereMonth("updated_at", now()->month)->count(),

            // Kategori Stats
            "kategori_teratas" => Barang::selectRaw("kategori, COUNT(*) as total")
                ->groupBy("kategori")
                ->orderBy("total", "desc")
                ->limit(5)
                ->get(),

            // Volume Bulanan
            "volume_bulanan" => Manifest::selectRaw("MONTH(tgl_manifest) as bulan, YEAR(tgl_manifest) as tahun, SUM(total_berat) as total_berat, COUNT(*) as jumlah_manifest")
                ->whereYear("tgl_manifest", ">=", date("Y") - 1)
                ->groupBy("tahun", "bulan")
                ->orderBy("tahun")
                ->orderBy("bulan")
                ->get(),

            // Top Routes
            "top_rute" => Booking::selectRaw("asal, tujuan, COUNT(*) as total")
                ->groupBy("asal", "tujuan")
                ->orderBy("total", "desc")
                ->limit(5)
                ->get(),

            // User Stats
            "total_user" => User::count(),
            "user_aktif" => User::where("is_active", true)->count(),
        ];
    }

    public function arusBarang(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['super_admin', 'admin', 'operator', 'manajer', 'upp', 'kantor_perhubungan'])) {
            return response()->json([
                "status" => "error",
                "message" => "Anda tidak memiliki izin",
                "code" => "ROLE_DENIED"
            ], 403);
        }

        $periodeAwal = $request->periode_awal ?? now()->subDays(30)->toDateString();
        $periodeAkhir = $request->periode_akhir ?? now()->toDateString();

        $data = Manifest::with("voyage.kapal", "booking.pelanggan", "detail.barang")
            ->whereBetween("tgl_manifest", [$periodeAwal, $periodeAkhir])
            ->orderBy("tgl_manifest", "desc")
            ->get();

        // Summary
        $summary = [
            "total_manifest" => $data->count(),
            "total_berat" => $data->sum("total_berat"),
            "total_volume" => $data->sum("total_volume"),
            "by_status" => $data->groupBy("status")->map->count(),
        ];

        return response()->json([
            "status" => "success",
            "data" => [
                "periode_awal" => $periodeAwal,
                "periode_akhir" => $periodeAkhir,
                "summary" => $summary,
                "manifests" => $data,
            ],
        ]);
    }

    public function trendBulanan(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['super_admin', 'admin', 'manajer', 'kantor_perhubungan'])) {
            return response()->json([
                "status" => "error",
                "message" => "Anda tidak memiliki izin",
                "code" => "ROLE_DENIED"
            ], 403);
        }

        $tahun = $request->tahun ?? date("Y");

        $data = Manifest::selectRaw("MONTH(tgl_manifest) as bulan, YEAR(tgl_manifest) as tahun, SUM(total_berat) as total_berat, SUM(total_volume) as total_volume, COUNT(*) as jumlah_manifest")
            ->whereYear("tgl_manifest", $tahun)
            ->groupBy("tahun", "bulan")
            ->orderBy("bulan")
            ->get();

        // Bandingkan dengan tahun sebelumnya
        $dataTahunLalu = Manifest::selectRaw("MONTH(tgl_manifest) as bulan, SUM(total_berat) as total_berat_tahun_lalu")
            ->whereYear("tgl_manifest", $tahun - 1)
            ->groupBy("bulan")
            ->orderBy("bulan")
            ->get()
            ->keyBy("bulan");

        foreach ($data as $item) {
            $item->total_berat_tahun_lalu = $dataTahunLalu[$item->bulan]->total_berat_tahun_lalu ?? 0;
            $item->pertumbuhan = $item->total_berat_tahun_lalu > 0 
                ? round((($item->total_berat - $item->total_berat_tahun_lalu) / $item->total_berat_tahun_lalu) * 100, 2)
                : 0;
        }

        return response()->json([
            "status" => "success",
            "data" => [
                "tahun" => $tahun,
                "trends" => $data,
            ]
        ]);
    }

    /**
     * Activity heatmap untuk dashboard (90 hari terakhir).
     * Mengembalikan map tanggal => jumlah aktivitas (manifest + booking + verifikasi + realisasi).
     */
    public function activityHeatmap(Request $request)
    {
        $user = $request->user();
        $tz = 'Asia/Makassar';
        $start = now()->subDays(89)->startOfDay();
        $end = now()->endOfDay();

        $counts = [];

        // Booking per hari
        $bookings = \App\Models\Booking::whereBetween('created_at', [$start, $end])->get(['created_at']);
        foreach ($bookings as $b) {
            $k = $b->created_at->timezone($tz)->toDateString();
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        // Manifest per hari (berdasarkan created_at atau tgl_manifest)
        $manifests = \App\Models\Manifest::whereBetween('created_at', [$start, $end])->get(['created_at']);
        foreach ($manifests as $m) {
            $k = $m->created_at->timezone($tz)->toDateString();
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        // Verifikasi per hari
        $verifs = \App\Models\VerifikasiUPP::whereBetween('tgl_verifikasi', [$start, $end])->get(['tgl_verifikasi']);
        foreach ($verifs as $v) {
            if (!$v->tgl_verifikasi) continue;
            $k = \Carbon\Carbon::parse($v->tgl_verifikasi)->timezone($tz)->toDateString();
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        // Realisasi per hari
        $real = \App\Models\RealisasiBongkarMuat::whereBetween('waktu_input', [$start, $end])->get(['waktu_input']);
        foreach ($real as $r) {
            $k = $r->waktu_input->timezone($tz)->toDateString();
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }

        return response()->json([
            "status" => "success",
            "data" => $counts,
        ]);
    }
}
