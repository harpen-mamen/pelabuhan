<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voyage;
use App\Models\Manifest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VoyageController extends Controller
{
    private array $manageRoles = ['super_admin', 'admin', 'operator'];

    public function index(Request $request)
    {
        $query = Voyage::with("kapal");
        if ($request->id_kapal) $query->where("id_kapal", $request->id_kapal);
        if ($request->status) $query->where("status", $request->status);
        if ($request->tanggal) $query->whereDate("tgl_berangkat", $request->tanggal);
        if ($request->boolean('aktif')) $query->where("status", "dijadwalkan");
        $data = $query->orderBy("tgl_berangkat", "desc")->paginate(20);
        return response()->json(["status" => "success", "data" => $data]);
    }

    public function store(Request $request)
    {
        $user = Auth::guard("api")->user();
        if (!in_array($user->role, $this->manageRoles)) {
            return response()->json(["status" => "error", "message" => "Role tidak diizinkan membuat voyage", "code" => "ROLE_VOYAGE_CREATE"], 403);
        }
        $validator = Validator::make($request->all(), [
            "id_kapal" => "required|exists:kapal,id_kapal",
            "nomor_voyage" => "required|string|unique:voyage,nomor_voyage|max:50",
            "rute" => "required|string|max:255",
            "tgl_berangkat" => "required|date|after_or_equal:today",
            "tgl_tiba" => "nullable|date|after:tgl_berangkat",
            "status" => "nullable|in:dijadwalkan,berlayar,sandar,selesai,dibatalkan",
            "estimasi_jam" => "nullable|integer|min:1|max:720",
            "pelabuhan_asal" => "nullable|string|max:100",
            "pelabuhan_transit" => "nullable|string|max:100",
            "pelabuhan_tujuan" => "nullable|string|max:100",
            "lat_asal" => "nullable|numeric|between:-90,90",
            "lng_asal" => "nullable|numeric|between:-180,180",
            "lat_transit" => "nullable|numeric|between:-90,90",
            "lng_transit" => "nullable|numeric|between:-180,180",
            "lat_tujuan" => "nullable|numeric|between:-90,90",
            "lng_tujuan" => "nullable|numeric|between:-180,180",
        ]);
        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal", "errors" => $validator->errors(), "code" => "VALIDATION_ERROR"], 422);
        }
        $payload = $request->only([
            "id_kapal", "nomor_voyage", "rute", "tgl_berangkat", "tgl_tiba", "status",
            "estimasi_jam",
            "pelabuhan_asal", "pelabuhan_transit", "pelabuhan_tujuan",
            "lat_asal", "lng_asal", "lat_transit", "lng_transit", "lat_tujuan", "lng_tujuan",
        ]);
        $payload["status"] = $payload["status"] ?? "dijadwalkan";
        $data = Voyage::create($payload);
        return response()->json(["status" => "success", "message" => "Voyage berhasil ditambahkan", "data" => $data->load("kapal")], 201);
    }

    public function show($id)
    {
        $data = Voyage::with(["kapal", "manifest.booking.pelanggan"])->find($id);
        if (!$data) return response()->json(["status" => "error", "message" => "Voyage tidak ditemukan", "code" => "VOYAGE_NOT_FOUND"], 404);
        return response()->json(["status" => "success", "data" => $data]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::guard("api")->user();
        if (!in_array($user->role, $this->manageRoles)) {
            return response()->json(["status" => "error", "message" => "Role tidak diizinkan mengubah voyage", "code" => "ROLE_VOYAGE_UPDATE"], 403);
        }
        $data = Voyage::find($id);
        if (!$data) return response()->json(["status" => "error", "message" => "Voyage tidak ditemukan", "code" => "VOYAGE_NOT_FOUND"], 404);
        if (in_array($data->status, ['selesai', 'dibatalkan'])) {
            return response()->json(["status" => "error", "message" => "Voyage sudah final", "code" => "VOYAGE_FINAL_STATE"], 422);
        }
        $validator = Validator::make($request->all(), [
            "id_kapal" => "sometimes|exists:kapal,id_kapal",
            "nomor_voyage" => "sometimes|string|unique:voyage,nomor_voyage," . $id . ",id_voyage|max:50",
            "rute" => "sometimes|string|max:255",
            "tgl_berangkat" => "sometimes|date",
            "tgl_tiba" => "nullable|date|after:tgl_berangkat",
            "status" => "sometimes|in:dijadwalkan,berlayar,sandar,selesai,dibatalkan",
            "estimasi_jam" => "nullable|integer|min:1|max:720",
            "pelabuhan_asal" => "nullable|string|max:100",
            "pelabuhan_transit" => "nullable|string|max:100",
            "pelabuhan_tujuan" => "nullable|string|max:100",
            "lat_asal" => "nullable|numeric|between:-90,90",
            "lng_asal" => "nullable|numeric|between:-180,180",
            "lat_transit" => "nullable|numeric|between:-90,90",
            "lng_transit" => "nullable|numeric|between:-180,180",
            "lat_tujuan" => "nullable|numeric|between:-90,90",
            "lng_tujuan" => "nullable|numeric|between:-180,180",
        ]);
        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal", "errors" => $validator->errors(), "code" => "VALIDATION_ERROR"], 422);
        }
        $data->update($request->only([
            "id_kapal", "nomor_voyage", "rute", "tgl_berangkat", "tgl_tiba", "status",
            "estimasi_jam",
            "pelabuhan_asal", "pelabuhan_transit", "pelabuhan_tujuan",
            "lat_asal", "lng_asal", "lat_transit", "lng_transit", "lat_tujuan", "lng_tujuan",
        ]));
        return response()->json(["status" => "success", "message" => "Voyage berhasil diupdate", "data" => $data->fresh()->load("kapal")]);
    }

    /**
     * Admin/operator menandai kapal benar-benar berangkat sekarang.
     * Otomatis meng-update semua manifest pada voyage ini menjadi 'dimuat'
     * dan booking menjadi 'dalam_perjalanan'.
     */
    public function berangkat($id)
    {
        $user = Auth::guard("api")->user();
        if (!in_array($user->role, $this->manageRoles)) {
            return response()->json(["status" => "error", "message" => "Role tidak diizinkan", "code" => "ROLE_VOYAGE_DEPART"], 403);
        }
        $voyage = Voyage::find($id);
        if (!$voyage) return response()->json(["status" => "error", "message" => "Voyage tidak ditemukan", "code" => "VOYAGE_NOT_FOUND"], 404);
        if (!$voyage->estimasi_jam) {
            return response()->json(["status" => "error", "message" => "Estimasi waktu tempuh belum diisi pada voyage ini", "code" => "VOYAGE_NO_ETA"], 422);
        }
        if ($voyage->status !== 'dijadwalkan') {
            return response()->json(["status" => "error", "message" => "Voyage hanya dapat diberangkatkan dari status dijadwalkan", "code" => "VOYAGE_INVALID_STATUS"], 422);
        }
        $manifests = Manifest::where("id_voyage", $id)->get();
        if ($manifests->isEmpty() || $manifests->contains(fn (Manifest $manifest) => $manifest->status !== 'dimuat')) {
            return response()->json([
                "status" => "error",
                "message" => "Semua manifest pada voyage harus disetujui UPP dan selesai dicatat muat oleh checker sebelum kapal berangkat.",
                "code" => "VOYAGE_MANIFESTS_NOT_READY"
            ], 422);
        }
        $voyage->update([
            "waktu_keberangkatan" => now(),
            "status" => "berlayar",
        ]);

        // Muat sudah dicatat oleh checker; keberangkatan hanya mengubah status booking.
        foreach ($manifests as $m) {
            if ($m->booking) {
                $m->booking->update(["status" => "dalam_perjalanan"]);
            }
        }

        return response()->json([
            "status" => "success",
            "message" => "Voyage ditandai berangkat. ETA dihitung otomatis.",
            "data" => [
                "voyage" => $voyage->fresh()->load("kapal"),
                "manifest_updated" => $manifests->count(),
                "eta" => \Carbon\Carbon::parse($voyage->waktu_keberangkatan)->addHours((int) $voyage->estimasi_jam)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Admin/operator menandai voyage tiba di tujuan.
     */
    public function tiba($id)
    {
        $user = Auth::guard("api")->user();
        if (!in_array($user->role, $this->manageRoles)) {
            return response()->json(["status" => "error", "message" => "Role tidak diizinkan", "code" => "ROLE_VOYAGE_ARRIVE"], 403);
        }
        $voyage = Voyage::find($id);
        if (!$voyage) return response()->json(["status" => "error", "message" => "Voyage tidak ditemukan", "code" => "VOYAGE_NOT_FOUND"], 404);
        if ($voyage->status !== "berlayar") {
            return response()->json(["status" => "error", "message" => "Voyage belum berlayar", "code" => "VOYAGE_NOT_DEPARTED"], 422);
        }
        $voyage->update([
            "waktu_tiba_aktual" => now(),
            "status" => "sandar",
            "tgl_tiba" => now()->toDateString(),
        ]);
        Manifest::where("id_voyage", $id)->where("status", "dimuat")->update(["status" => "dalam_bongkar"]);
        return response()->json(["status" => "success", "message" => "Voyage tiba di tujuan", "data" => $voyage->fresh()->load("kapal")]);
    }

    public function destroy($id)
    {
        $user = Auth::guard("api")->user();
        if (!in_array($user->role, ['super_admin', 'admin'], true)) {
            return response()->json(["status" => "error", "message" => "Hanya admin yang dapat menghapus", "code" => "ROLE_VOYAGE_DELETE"], 403);
        }
        $data = Voyage::find($id);
        if (!$data) return response()->json(["status" => "error", "message" => "Voyage tidak ditemukan", "code" => "VOYAGE_NOT_FOUND"], 404);
        if (Manifest::where("id_voyage", $id)->exists()) {
            return response()->json(["status" => "error", "message" => "Voyage sudah memiliki manifest", "code" => "VOYAGE_HAS_MANIFEST"], 422);
        }
        $data->delete();
        return response()->json(["status" => "success", "message" => "Voyage berhasil dihapus"]);
    }

    public function cekKapasitas($id)
    {
        $voyage = Voyage::with("kapal")->find($id);
        if (!$voyage) return response()->json(["status" => "error", "message" => "Voyage tidak ditemukan", "code" => "VOYAGE_NOT_FOUND"], 404);
        $kapasitas = $voyage->kapal->kapasitas_dwt;
        $totalManifest = Manifest::where("id_voyage", $id)->whereIn("status", ["menunggu_verifikasi", "disetujui"])->sum("total_berat");
        $sisa = $kapasitas - $totalManifest;
        return response()->json(["status" => "success", "data" => [
            "id_voyage" => $id, "nomor_voyage" => $voyage->nomor_voyage,
            "kapal" => ["id_kapal" => $voyage->kapal->id_kapal, "nama" => $voyage->kapal->nama_kapal, "kapasitas_dwt" => $kapasitas],
            "tgl_berangkat" => $voyage->tgl_berangkat, "status" => $voyage->status,
            "total_manifest_aktif" => $totalManifest, "sisa_kapasitas" => $sisa,
            "persentase_terpakai" => round(($totalManifest / $kapasitas) * 100, 2),
            "is_available" => $sisa > 0 && in_array($voyage->status, ["dijadwalkan", "sandar"]),
        ]]);
    }

    public function aktif()
    {
        $data = Voyage::with("kapal")->where("status", "dijadwalkan")
            ->whereDate("tgl_berangkat", ">=", today())->orderBy("tgl_berangkat")->get();
        return response()->json(["status" => "success", "data" => $data]);
    }
}
