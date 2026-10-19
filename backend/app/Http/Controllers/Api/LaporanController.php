<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Manifest;
use App\Models\RealisasiBongkarMuat;
use App\Models\Laporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LaporanController extends Controller
{
    public function arusBarang(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "periode_awal" => "required|date",
            "periode_akhir" => "required|date|after_or_equal:periode_awal",
        ]);

        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal", "data" => $validator->errors()], 422);
        }

        $data = Manifest::with(["voyage.kapal", "booking.pelanggan.perusahaan", "detail.barang"])
            ->whereBetween("tgl_manifest", [$request->periode_awal, $request->periode_akhir])
            ->orderBy("tgl_manifest")
            ->get();

        // Statistik agregat
        $totalBerat = $data->sum("total_berat");
        $totalVolume = $data->sum("total_volume");
        $byKategori = [];
        foreach ($data as $m) {
            foreach ($m->detail as $d) {
                $kat = $d->barang->kategori ?? "lainnya";
                if (!isset($byKategori[$kat])) $byKategori[$kat] = 0;
                $byKategori[$kat] += $d->barang->berat ?? 0;
            }
        }

        Laporan::create([
            "jenis_laporan" => "arus_barang",
            "periode_awal" => $request->periode_awal,
            "periode_akhir" => $request->periode_akhir,
            "id_user_generate" => Auth::guard("api")->user()->id_user,
        ]);

        return response()->json([
            "status" => "success",
            "data" => [
                "periode_awal" => $request->periode_awal,
                "periode_akhir" => $request->periode_akhir,
                "manifests" => $data,
                "summary" => [
                    "total_berat" => $totalBerat,
                    "total_volume" => $totalVolume,
                    "by_kategori" => $byKategori,
                    "jumlah_manifest" => $data->count(),
                ],
            ],
        ]);
    }

    public function selisih(Request $request)
    {
        $data = RealisasiBongkarMuat::with(["manifestDetail.barang", "manifestDetail.manifest.voyage.kapal", "checker"])
            ->whereNotNull("keterangan_selisih")
            ->orderBy("waktu_input", "desc")
            ->get();

        return response()->json(["status" => "success", "data" => $data]);
    }

    public function manifest(Request $request)
    {
        $query = Manifest::with(["voyage.kapal", "booking.pelanggan", "detail.barang", "verifikasi"]);
        if ($request->id_voyage) $query->where("id_voyage", $request->id_voyage);
        $data = $query->orderBy("tgl_manifest", "desc")->get();
        return response()->json(["status" => "success", "data" => $data]);
    }

    /**
     * Laporan kepatuhan manifest per periode (%).
     */
    public function kepatuhan(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            "periode_awal" => "required|date",
            "periode_akhir" => "required|date|after_or_equal:periode_awal",
        ]);
        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal", "data" => $validator->errors()], 422);
        }

        $manifests = Manifest::whereBetween("tgl_manifest", [$request->periode_awal, $request->periode_akhir])->get();
        $total = $manifests->count();
        $sesuai = $manifests->whereNotIn("status", ["perlu_klarifikasi","ditolak"])->count();
        $selisih = $manifests->where("status", "perlu_klarifikasi")->count();
        $ditolak = $manifests->where("status", "ditolak")->count();
        $persen = $total > 0 ? round(($sesuai / $total) * 100, 2) : 0;

        // Tren per bulan dalam periode
        $tren = $manifests->groupBy(function ($m) {
            return \Carbon\Carbon::parse($m->tgl_manifest)->format("Y-m");
        })->map(function ($group, $key) {
            return [
                "bulan" => $key,
                "sesuai" => $group->whereNotIn("status", ["perlu_klarifikasi","ditolak"])->count(),
                "selisih" => $group->where("status", "perlu_klarifikasi")->count(),
                "total" => $group->count(),
            ];
        })->values();

        return response()->json([
            "status" => "success",
            "data" => [
                "periode_awal" => $request->periode_awal,
                "periode_akhir" => $request->periode_akhir,
                "total_manifest" => $total,
                "manifest_sesuai" => $sesuai,
                "manifest_selisih" => $selisih,
                "manifest_ditolak" => $ditolak,
                "persen_kepatuhan" => $persen,
                "tren" => $tren,
            ],
        ]);
    }

    /**
     * Laporan PNBP per periode.
     * PNBP dihitung berdasarkan jumlah manifest yang disetujui/selesai.
     * (Tarif PNBP aktual akan menggunakan master `tarif` setelahnya)
     */
    public function pnbp(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            "periode_awal" => "required|date",
            "periode_akhir" => "required|date|after_or_equal:periode_awal",
        ]);
        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal", "data" => $validator->errors()], 422);
        }

        $manifests = Manifest::whereBetween("tgl_manifest", [$request->periode_awal, $request->periode_akhir])
            ->whereIn("status", ["disetujui", "selesai"])
            ->get();

        // Estimasi PNBP: Rp 350.000 per manifest disetujui (placeholder;
        // akan menggunakan tabel `tarif` setelah master tersedia)
        $tarifPnbpPerManifest = 350000;

        $rincian = $manifests->map(function ($m) use ($tarifPnbpPerManifest) {
            return [
                "id_manifest" => $m->id_manifest,
                "nomor_manifest" => $m->nomor_manifest,
                "tgl_manifest" => $m->tgl_manifest,
                "nilai_pnbp" => $tarifPnbpPerManifest,
                "status_setor" => "Belum disetor",
            ];
        });

        $totalPnbp = $rincian->sum("nilai_pnbp");
        $jumlahManifest = $rincian->count();
        $rataRata = $jumlahManifest > 0 ? round($totalPnbp / $jumlahManifest, 2) : 0;

        return response()->json([
            "status" => "success",
            "data" => [
                "periode_awal" => $request->periode_awal,
                "periode_akhir" => $request->periode_akhir,
                "total_pnbp" => $totalPnbp,
                "jumlah_manifest" => $jumlahManifest,
                "rata_rata" => $rataRata,
                "rincian" => $rincian,
            ],
        ]);
    }
}
