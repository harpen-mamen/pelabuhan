<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Manifest;
use App\Models\ManifestDetail;
use App\Models\Notifikasi;
use App\Models\User;
use App\Models\Voyage;
use App\Models\VerifikasiUPP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ManifestController extends Controller
{
    /**
     * ========================================================================
     * MANIFEST STATUS FLOW
     * ========================================================================
     * draft -> menunggu_verifikasi -> disetujui -> selesai
     *                          \\-> ditolak -> draft (revisi)
     *                          \\-> perlu_klarifikasi -> selesai
     * ========================================================================
     */
    
    private array $createRoles = ['admin', 'operator'];
    private array $verifikasiRoles = ['upp'];
    private array $submitRoles = ['admin', 'operator'];

    public function index(Request $request)
    {
        $user = Auth::guard("api")->user();
        $query = Manifest::with(["voyage.kapal", "booking.pelanggan", "pembuat", "detail.barang", "verifikasi"]);

        if ($request->status) $query->where("status", $request->status);
        if ($request->id_voyage) $query->where("id_voyage", $request->id_voyage);
        if ($request->id_booking) $query->where("id_booking", $request->id_booking);
        if ($user->role === 'checker') {
            $query->whereIn('status', ['disetujui', 'dimuat', 'dalam_bongkar']);
        }

        $data = $query->orderBy("created_at", "desc")->paginate(20);
        return response()->json(["status" => "success", "data" => $data]);
    }

    public function store(Request $request)
    {
        $user = Auth::guard("api")->user();

        // ====================================================================
        // VALIDASI ROLE: Siapa yang boleh membuat manifest
        // ====================================================================
        if (!in_array($user->role, $this->createRoles)) {
            return response()->json([
                "status" => "error",
                "message" => "Role \"{$user->role}\" tidak diizinkan membuat manifest",
                "code" => "ROLE_MANIFEST_CREATE"
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            "id_voyage" => "required|exists:voyage,id_voyage",
            "id_booking" => "required|exists:booking,id_booking",
            "barang_ids" => "required|array|min:1",
            "barang_ids.*" => "exists:barang,id_barang",
            "jumlah_manifest" => "required|array",
            "catatan" => "nullable|string|max:500",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => "Validasi gagal",
                "errors" => $validator->errors(),
                "code" => "VALIDATION_ERROR"
            ], 422);
        }

        // ====================================================================
        // VALIDASI: Booking exists dan status-nya valid
        // ====================================================================
        $booking = \App\Models\Booking::with('manifest')->find($request->id_booking);
        if (!$booking) {
            return response()->json([
                "status" => "error",
                "message" => "Booking tidak ditemukan",
                "code" => "BOOKING_NOT_FOUND"
            ], 404);
        }

        // ====================================================================
        // VALIDASI: Booking belum punya manifest
        // ====================================================================
        if ($booking->manifest) {
            return response()->json([
                "status" => "error",
                "message" => "Booking sudah memiliki manifest ({$booking->manifest->nomor_manifest}). Satu booking hanya boleh memiliki satu manifest.",
                "code" => "MANIFEST_EXISTS"
            ], 422);
        }

        // ====================================================================
        // VALIDASI: Status booking harus 'diproses' atau 'manifest'
        // ====================================================================
        if (!in_array($booking->status, ['diproses', 'manifest'])) {
            return response()->json([
                "status" => "error",
                "message" => "Booking harus berstatus \"diproses\" terlebih dahulu. Status saat ini: \"{$booking->status}\"",
                "code" => "BOOKING_INVALID_STATUS"
            ], 422);
        }

        // ====================================================================
        // VALIDASI: Barang milik booking yang benar
        // ====================================================================
        $bookingBarangIds = $booking->barang->pluck('id_barang')->toArray();
        $requestedBarangIds = $request->barang_ids;
        $invalidBarang = array_diff($requestedBarangIds, $bookingBarangIds);
        
        if (!empty($invalidBarang)) {
            return response()->json([
                "status" => "error",
                "message" => "Beberapa barang tidak milik booking ini",
                "code" => "INVALID_BARANG"
            ], 422);
        }

        // ====================================================================
        // VALIDASI: Voyage exists dan aktif
        // ====================================================================
        $voyage = Voyage::with("kapal")->find($request->id_voyage);
        if (!$voyage) {
            return response()->json([
                "status" => "error",
                "message" => "Voyage tidak ditemukan",
                "code" => "VOYAGE_NOT_FOUND"
            ], 404);
        }

        if ($voyage->status !== 'dijadwalkan' && $voyage->status !== 'sandar') {
            return response()->json([
                "status" => "error",
                "message" => "Voyage tidak aktif. Status: {$voyage->status}",
                "code" => "VOYAGE_INACTIVE"
            ], 422);
        }

        DB::beginTransaction();
        try {
            $totalBerat = 0;
            $totalVolume = 0;
            foreach ($request->barang_ids as $idx => $barangId) {
                $barang = Barang::findOrFail($barangId);
                $qty = $request->jumlah_manifest[$idx] ?? 1;
                $totalBerat += $barang->berat * $qty;
                $totalVolume += $barang->volume * $qty;
            }

            // ====================================================================
            // VALIDASI KAPASITAS: Cek kapasitas voyage, bukan kapal langsung
            // ====================================================================
            $existingBerat = Manifest::where("id_voyage", $request->id_voyage)
                ->whereIn("status", ["menunggu_verifikasi", "disetujui"])
                ->sum("total_berat");
            
            $availableCapacity = $voyage->kapal->kapasitas_dwt - $existingBerat;
            
            if ($totalBerat > $availableCapacity) {
                return response()->json([
                    "status" => "error",
                    "message" => "Kapasitas tidak cukup. Available: {$availableCapacity} kg, Requested: {$totalBerat} kg",
                    "code" => "CAPACITY_EXCEEDED"
                ], 422);
            }

            // Generate nomor manifest
            $nomorManifest = "MNF-TAHUNA-" . date("Ymd") . "-" . str_pad(
                Manifest::whereDate("tgl_manifest", today())->count() + 1,
                4, "0", STR_PAD_LEFT
            );

            $manifest = Manifest::create([
                "nomor_manifest" => $nomorManifest,
                "id_voyage" => $request->id_voyage,
                "id_booking" => $request->id_booking,
                "id_user_pembuat" => $user->id_user,
                "tgl_manifest" => now()->toDateString(),
                "total_berat" => $totalBerat,
                "total_volume" => $totalVolume,
                "status" => "menunggu_verifikasi",
                "catatan" => $request->catatan,
            ]);

            // Create manifest details
            foreach ($request->barang_ids as $idx => $barangId) {
                ManifestDetail::create([
                    "id_manifest" => $manifest->id_manifest,
                    "id_barang" => $barangId,
                    "jumlah_manifest" => $request->jumlah_manifest[$idx] ?? 1,
                ]);
            }

            // Update booking status ke 'manifest'
            $booking->update(["status" => "manifest"]);

            // Kirim notifikasi ke UPP
            $upps = User::where("role", "upp")->where("is_active", true)->get();
            foreach ($upps as $upp) {
                Notifikasi::create([
                    "id_user" => $upp->id_user,
                    "judul" => "Manifest Baru untuk Verifikasi",
                    "pesan" => "Manifest {$nomorManifest} menunggu verifikasi",
                    "tipe" => "warning",
                    "link" => "/manifest/{$manifest->id_manifest}",
                    "waktu" => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => "Manifest berhasil dibuat dengan nomor {$nomorManifest}",
                "data" => $manifest->load("detail.barang", "voyage.kapal", "booking"),
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status" => "error",
                "message" => "Gagal membuat manifest: " . $e->getMessage(),
                "code" => "MANIFEST_CREATE_ERROR"
            ], 500);
        }
    }

    public function show($id)
    {
        $data = Manifest::with([
            "voyage.kapal", "booking.pelanggan.perusahaan", "pembuat", 
            "detail.barang", "detail.realisasi", "verifikasi.petugasUpp"
        ])->find($id);

        if (!$data) {
            return response()->json([
                "status" => "error",
                "message" => "Manifest tidak ditemukan",
                "code" => "MANIFEST_NOT_FOUND"
            ], 404);
        }

        return response()->json(["status" => "success", "data" => $data]);
    }

    /**
     * Submit manifest untuk verifikasi
     */
    public function submit($id)
    {
        $user = Auth::guard("api")->user();

        if (!in_array($user->role, $this->submitRoles)) {
            return response()->json([
                "status" => "error",
                "message" => "Role \"{$user->role}\" tidak diizinkan submit manifest",
                "code" => "ROLE_MANIFEST_SUBMIT"
            ], 403);
        }

        $manifest = Manifest::find($id);

        if (!$manifest) {
            return response()->json([
                "status" => "error",
                "message" => "Manifest tidak ditemukan",
                "code" => "MANIFEST_NOT_FOUND"
            ], 404);
        }

        // ====================================================================
        // VALIDASI: Status harus 'draft' atau 'ditolak' untuk bisa submit
        // ====================================================================
        if (!in_array($manifest->status, ['draft', 'ditolak'])) {
            return response()->json([
                "status" => "error",
                "message" => "Manifest tidak bisa disubmit. Status saat ini: \"{$manifest->status}\"",
                "code" => "MANIFEST_INVALID_STATUS"
            ], 422);
        }

        $manifest->update(["status" => "menunggu_verifikasi"]);

        // Notifikasi ke UPP
        $upps = User::where("role", "upp")->where("is_active", true)->get();
        foreach ($upps as $upp) {
            Notifikasi::create([
                "id_user" => $upp->id_user,
                "judul" => "Manifest Diajukan Kembali",
                "pesan" => "Manifest {$manifest->nomor_manifest} diajukan untuk verifikasi ulang",
                "tipe" => "info",
                "link" => "/manifest/{$manifest->id_manifest}",
                "waktu" => now(),
            ]);
        }

        return response()->json([
            "status" => "success",
            "message" => "Manifest disubmit untuk verifikasi",
            "data" => $manifest
        ]);
    }

    /**
     * Verifikasi manifest oleh UPP
     */
    public function verifikasi(Request $request, $id)
    {
        $user = Auth::guard("api")->user();

        // ====================================================================
        // VALIDASI ROLE: Hanya UPP dan Admin yang boleh verifikasi
        // ====================================================================
        if (!in_array($user->role, $this->verifikasiRoles)) {
            return response()->json([
                "status" => "error",
                "message" => "Role \"{$user->role}\" tidak diizinkan melakukan verifikasi manifest",
                "code" => "ROLE_MANIFEST_VERIFIKASI"
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            "status_verifikasi" => "required|in:disetujui,ditolak,revisi",
            "catatan" => "nullable|string|max:500",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => "Validasi gagal",
                "errors" => $validator->errors(),
                "code" => "VALIDATION_ERROR"
            ], 422);
        }

        $manifest = Manifest::with('booking')->find($id);

        if (!$manifest) {
            return response()->json([
                "status" => "error",
                "message" => "Manifest tidak ditemukan",
                "code" => "MANIFEST_NOT_FOUND"
            ], 404);
        }

        // ====================================================================
        // VALIDASI: Manifest harus menunggu verifikasi
        // ====================================================================
        if ($manifest->status !== 'menunggu_verifikasi') {
            return response()->json([
                "status" => "error",
                "message" => "Manifest tidak dalam status menunggu verifikasi. Status: \"{$manifest->status}\"",
                "code" => "MANIFEST_NOT_PENDING"
            ], 422);
        }

        // ====================================================================
        // VALIDASI: Jika ditolak harus ada catatan
        // ====================================================================
        if ($request->status_verifikasi === 'ditolak' && !$request->catatan) {
            return response()->json([
                "status" => "error",
                "message" => "Harus memberikan alasan penolakan",
                "code" => "REASON_REQUIRED"
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create verification record
            $verifikasi = VerifikasiUPP::create([
                "id_manifest" => $manifest->id_manifest,
                "id_petugas_upp" => $user->id_user,
                "status_verifikasi" => $request->status_verifikasi,
                "catatan" => $request->catatan,
                "tgl_verifikasi" => now(),
            ]);

            // Update manifest status berdasarkan hasil verifikasi
            $newManifestStatus = match($request->status_verifikasi) {
                'disetujui' => 'disetujui',
                'ditolak' => 'ditolak',
                'revisi' => 'draft',
                default => $manifest->status,
            };
            $manifest->update(["status" => $newManifestStatus]);

            // ====================================================================
            // UPDATE BOOKING STATUS BERDASARKAN HASIL VERIFIKASI
            // ====================================================================
            if ($manifest->booking) {
                if ($request->status_verifikasi === 'disetujui') {
                    // Jika disetujui, booking siap dimuat
                    $manifest->booking->update(["status" => "siap_dimuat"]);
                } elseif ($request->status_verifikasi === 'ditolak') {
                    // Jika ditolak, booking kembali ke 'diproses' untuk revisi
                    $manifest->booking->update(["status" => "diproses"]);
                }
                // Jika revisi, booking tetap 'manifest'
            }

            // Notifikasi ke operator/pembuat manifest
            Notifikasi::create([
                "id_user" => $manifest->id_user_pembuat,
                "judul" => "Hasil Verifikasi Manifest",
                "pesan" => "Manifest {$manifest->nomor_manifest} telah diverifikasi: {$request->status_verifikasi}" .
                    ($request->catatan ? ". Catatan: {$request->catatan}" : ""),
                "tipe" => $request->status_verifikasi === "disetujui" ? "success" : "error",
                "link" => "/manifest/{$manifest->id_manifest}",
                "waktu" => now(),
            ]);

            // Jika disetujui, notifikasi ke checker
            if ($request->status_verifikasi === 'disetujui') {
                $checkers = User::where("role", "checker")->where("is_active", true)->get();
                foreach ($checkers as $checker) {
                    Notifikasi::create([
                        "id_user" => $checker->id_user,
                        "judul" => "Manifest Disetujui - Siap Muat",
                        "pesan" => "Manifest {$manifest->nomor_manifest} siap untuk proses muat",
                        "tipe" => "info",
                        "link" => "/manifest/{$manifest->id_manifest}",
                        "waktu" => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => "Verifikasi berhasil dicatat",
                "data" => [
                    "manifest" => $manifest->fresh()->load("verifikasi"),
                    "booking_status" => $manifest->booking ? $manifest->booking->status : null,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status" => "error",
                "message" => "Gagal menyimpan verifikasi: " . $e->getMessage(),
                "code" => "VERIFIKASI_ERROR"
            ], 500);
        }
    }

    /**
     * Get statistics untuk dashboard UPP
     */
    public function statistics()
    {
        $user = Auth::guard("api")->user();

        if (!in_array($user->role, $this->verifikasiRoles)) {
            return response()->json([
                "status" => "error",
                "message" => "Anda tidak memiliki izin",
                "code" => "ROLE_DENIED"
            ], 403);
        }

        $data = [
            "menunggu_verifikasi" => Manifest::where("status", "menunggu_verifikasi")->count(),
            "disetujui_hari_ini" => Manifest::where("status", "disetujui")
                ->whereDate("updated_at", today())->count(),
            "ditolak_hari_ini" => Manifest::where("status", "ditolak")
                ->whereDate("updated_at", today())->count(),
            "total_disetujui_bulan_ini" => Manifest::where("status", "disetujui")
                ->whereMonth("updated_at", now()->month)->count(),
            "total_ditolak_bulan_ini" => Manifest::where("status", "ditolak")
                ->whereMonth("updated_at", now()->month)->count(),
        ];

        return response()->json(["status" => "success", "data" => $data]);
    }
}
