<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Models\ManifestDetail;
use App\Models\Notifikasi;
use App\Models\RealisasiBongkarMuat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RealisasiController extends Controller
{
    /**
     * ========================================================================
     * ROLE YANG BOLEH INPUT REALISASI
     * ========================================================================
     */
    private array $createRoles = ['super_admin', 'admin', 'checker'];
    private array $readRoles = ['super_admin', 'admin', 'operator', 'upp', 'checker', 'manajer'];

    public function index($manifestId = null)
    {
        $user = Auth::guard("api")->user();

        if (!in_array($user->role, $this->readRoles)) {
            return response()->json([
                "status" => "error",
                "message" => "Role \"{$user->role}\" tidak diizinkan melihat data realise",
                "code" => "ROLE_REALISASI_READ"
            ], 403);
        }

        $query = RealisasiBongkarMuat::with([
            "manifestDetail.barang", 
            "manifestDetail.manifest.voyage.kapal",
            "manifestDetail.manifest.booking",
            "checker"
        ]);

        if ($manifestId) {
            $query->whereHas("manifestDetail", function($q) use ($manifestId) {
                $q->where("id_manifest", $manifestId);
            });
        }

        // Filter berdasarkan tanggal
        if (request('tanggal')) {
            $query->whereDate("waktu_input", request('tanggal'));
        }

        // Filter berdasarkan jenis
        if (request('jenis')) {
            $query->where("jenis", request('jenis'));
        }

        $data = $query->orderBy("waktu_input", "desc")->paginate(20);
        return response()->json(["status" => "success", "data" => $data]);
    }

    public function store(Request $request)
    {
        $user = Auth::guard("api")->user();

        // ====================================================================
        // VALIDASI ROLE: Siapa yang boleh input realise
        // ====================================================================
        if (!in_array($user->role, $this->createRoles)) {
            return response()->json([
                "status" => "error",
                "message" => "Role \"{$user->role}\" tidak diizinkan menginput realise",
                "code" => "ROLE_REALISASI_CREATE"
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            "id_manifest_detail" => "required|exists:manifest_detail,id_manifest_detail",
            "jumlah_realisasi" => "required|numeric|min:0",
            "jenis" => "required|in:muat,bongkar",
            "keterangan_selisih" => "nullable|string|max:500",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => "Validasi gagal",
                "errors" => $validator->errors(),
                "code" => "VALIDATION_ERROR"
            ], 422);
        }

        // NOTE: $user sudah dideklarasi di awal method store()

        $detail = ManifestDetail::with("barang", "manifest.booking.pelanggan")->findOrFail($request->id_manifest_detail);
        $manifest = $detail->manifest;
        $booking = $manifest->booking;

        // Validasi dilakukan sebelum transaksi agar setiap penolakan tidak
        // meninggalkan transaksi terbuka pada koneksi database.
        $muatAllowed = in_array($manifest->status, ['disetujui', 'perlu_klarifikasi'], true);
        $bongkarAllowed = in_array($manifest->status, ['dimuat', 'dalam_bongkar', 'perlu_klarifikasi'], true)
            && $booking && in_array($booking->status, ['dalam_perjalanan', 'diterima'], true);
        if ($request->jenis === 'muat' && !$muatAllowed) {
            return response()->json([
                "status" => "error",
                "message" => "Muat hanya dapat dicatat setelah UPP menyetujui manifest. Status saat ini: \"{$manifest->status}\"",
                "code" => "MANIFEST_NOT_APPROVED"
            ], 422);
        }
        if ($request->jenis === 'bongkar' && !$bongkarAllowed) {
            return response()->json([
                "status" => "error",
                "message" => "Bongkar hanya dapat dicatat setelah voyage tiba dan barang dalam perjalanan.",
                "code" => "BOOKING_NOT_IN_TRANSIT"
            ], 422);
        }

        $existingRealisasi = RealisasiBongkarMuat::where("id_manifest_detail", $request->id_manifest_detail)
            ->where("jenis", $request->jenis)
            ->exists();
        if ($existingRealisasi) {
            return response()->json([
                "status" => "error",
                "message" => "Realisasi untuk barang ini dengan jenis \"{$request->jenis}\" sudah ada",
                "code" => "REALISASI_EXISTS"
            ], 422);
        }

        DB::beginTransaction();
        try {

            $jumlahManifest = $detail->jumlah_manifest;
            $jumlahRealisasi = $request->jumlah_realisasi;
            $selisih = $jumlahManifest - $jumlahRealisasi;

            // ====================================================================
            // PROSES SELISIH
            // ====================================================================
            $keteranganFinal = null;
            
            if (abs($selisih) > 0.01) {
                $autoKeterangan = "Selisih: " . abs($selisih) . " (manifest: {$jumlahManifest}, realise: {$jumlahRealisasi})";
                $keteranganFinal = $autoKeterangan . ($request->keterangan_selisih ? " - {$request->keterangan_selisih}" : "");

                // Update manifest status jika ada selisih
                if ($manifest->status === 'disetujui') {
                    $manifest->update(["status" => "perlu_klarifikasi"]);
                }

                // Notifikasi ke operator dan UPP
                $notifUsers = [];
                if ($manifest->id_user_pembuat) $notifUsers[] = $manifest->id_user_pembuat;
                $upps = User::where("role", "upp")->where("is_active", true)->pluck("id_user")->toArray();
                $operators = User::where("role", "operator")->where("is_active", true)->pluck("id_user")->toArray();
                $notifUsers = array_merge($notifUsers, $upps, $operators);

                foreach (array_unique($notifUsers) as $uid) {
                    Notifikasi::create([
                        "id_user" => $uid,
                        "judul" => "Selisih Data Muatan",
                        "pesan" => "Manifest {$manifest->nomor_manifest}: {$autoKeterangan}",
                        "tipe" => "warning",
                        "link" => "/manifest/{$manifest->id_manifest}",
                        "waktu" => now(),
                    ]);
                }
            }

            $realisasi = RealisasiBongkarMuat::create([
                "id_manifest_detail" => $request->id_manifest_detail,
                "id_checker" => $user->id_user,
                "jumlah_realisasi" => $jumlahRealisasi,
                "jenis" => $request->jenis,
                "keterangan_selisih" => $keteranganFinal,
                "waktu_input" => now(),
            ]);

            // Update barang status
            if ($detail->barang) {
                $newStatus = $request->jenis === "muat" ? "dimuat" : "dibongkar";
                $detail->barang->update(["status_muatan" => $newStatus]);
            }

            // ====================================================================
            // UPDATE BOOKING STATUS
            // ====================================================================
            if ($booking) {
                if ($request->jenis === "bongkar") {
                    $booking->update(["status" => "dalam_perjalanan"]);
                }

                // ====================================================================
                // CEK APAKAH SEMUA BARANG SUDAH DIMUAT/DIBONGKAR
                // ====================================================================
                $this->checkAndUpdateFinalStatus($manifest, $request->jenis);

                // Notifikasi ke pelanggan
                if ($booking->pelanggan && $booking->pelanggan->id_user) {
                    $statusText = $request->jenis === "muat" ? "sedang dimuat ke kapal" : "telah tiba di tujuan";
                    Notifikasi::create([
                        "id_user" => $booking->pelanggan->id_user,
                        "judul" => "Update Status Pengiriman",
                        "pesan" => "Barang Anda {$statusText}",
                        "tipe" => $request->jenis === "bongkar" ? "success" : "info",
                        "link" => "/tracking/{$booking->kode_booking}",
                        "waktu" => now(),
                    ]);
                }
            }

            // Update manifest status jika semua OK
            $this->checkManifestCompletion($manifest);

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => "Realise berhasil dicatat",
                "data" => [
                    "realisasi" => $realisasi->load("manifestDetail.barang"),
                    "selisih" => abs($selisih) > 0.01 ? abs($selisih) : null,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                "status" => "error",
                "message" => "Gagal menyimpan realise: " . $e->getMessage(),
                "code" => "REALISASI_CREATE_ERROR"
            ], 500);
        }
    }

    /**
     * Cek apakah semua barang sudah realise dan update status manifest
     */
    private function checkAndUpdateFinalStatus(Manifest $manifest, string $jenis): void
    {
        $details = $manifest->detail;
        $allProcessed = true;

        foreach ($details as $detail) {
            $existing = RealisasiBongkarMuat::where("id_manifest_detail", $detail->id_manifest_detail)
                ->where("jenis", $jenis)
                ->exists();
            
            if (!$existing) {
                $allProcessed = false;
                break;
            }
        }

        if ($allProcessed) {
            if ($jenis === 'muat') {
                // Muat selesai belum berarti pengiriman selesai; kapal harus
                // berangkat dahulu sebelum barang masuk status perjalanan.
                $manifest->update(["status" => "dimuat"]);
                if ($manifest->booking) {
                    $manifest->booking->update(["status" => "dimuat"]);
                }
            } elseif ($jenis === 'bongkar') {
                // Semua barang sudah dibongkar, manifest selesai
                $manifest->update(["status" => "selesai"]);
                
                // Update booking ke 'diterima'
                if ($manifest->booking) {
                    $manifest->booking->update(["status" => "diterima"]);
                }
            }
        }
    }

    /**
     * Cek apakah manifest sudah complete (semua realise tanpa selisih)
     */
    private function checkManifestCompletion(Manifest $manifest): void
    {
        $hasSelisih = RealisasiBongkarMuat::whereHas("manifestDetail", function($q) use ($manifest) {
                $q->where("id_manifest", $manifest->id_manifest);
            })
            ->where("keterangan_selisih", "LIKE", "%Selisih%")
            ->exists();

        if (!$hasSelisih && $manifest->status === 'perlu_klarifikasi') {
            $hasBongkar = RealisasiBongkarMuat::whereHas("manifestDetail", function ($q) use ($manifest) {
                $q->where("id_manifest", $manifest->id_manifest);
            })->where("jenis", "bongkar")->exists();
            $hasMuat = RealisasiBongkarMuat::whereHas("manifestDetail", function ($q) use ($manifest) {
                $q->where("id_manifest", $manifest->id_manifest);
            })->where("jenis", "muat")->exists();
            $manifest->update(["status" => $hasBongkar ? "selesai" : ($hasMuat ? "dimuat" : "disetujui")]);
        }
    }

    /**
     * Get daftar manifest yang perlu klarifikasi
     */
    public function selisih()
    {
        $user = Auth::guard("api")->user();

        if (!in_array($user->role, ['super_admin', 'admin', 'operator', 'upp'])) {
            return response()->json([
                "status" => "error",
                "message" => "Anda tidak memiliki izin",
                "code" => "ROLE_DENIED"
            ], 403);
        }

        $manifests = Manifest::with(["detail.barang", "detail.realisasi", "voyage.kapal", "booking"])
            ->whereHas("detail.realisasi", function ($query) {
                $query->whereNotNull("keterangan_selisih");
            })
            ->orderBy("created_at", "desc")
            ->get();

        // Attach selisih info
        foreach ($manifests as $manifest) {
            $manifest->selisih_details = RealisasiBongkarMuat::whereHas("manifestDetail", function($q) use ($manifest) {
                $q->where("id_manifest", $manifest->id_manifest);
            })
            ->where("keterangan_selisih", "LIKE", "%Selisih%")
            ->with("manifestDetail.barang")
            ->get();
        }

        return response()->json(["status" => "success", "data" => $manifests]);
    }

    /**
     * Statistics untuk dashboard checker
     */
    public function statistics()
    {
        $user = Auth::guard("api")->user();

        if (!in_array($user->role, $this->createRoles)) {
            return response()->json([
                "status" => "error",
                "message" => "Anda tidak memiliki izin",
                "code" => "ROLE_DENIED"
            ], 403);
        }

        $data = [
            "realisasi_hari_ini" => RealisasiBongkarMuat::whereDate("waktu_input", today())->count(),
            "muat_hari_ini" => RealisasiBongkarMuat::whereDate("waktu_input", today())->where("jenis", "muat")->count(),
            "bongkar_hari_ini" => RealisasiBongkarMuat::whereDate("waktu_input", today())->where("jenis", "bongkar")->count(),
            "manifest_siap_muat" => Manifest::where("status", "disetujui")->count(),
            "manifest_perlu_klarifikasi" => Manifest::where("status", "perlu_klarifikasi")->count(),
        ];

        return response()->json(["status" => "success", "data" => $data]);
    }
}
