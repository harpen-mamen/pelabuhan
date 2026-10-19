<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Booking;
use App\Models\Manifest;
use App\Models\Notifikasi;
use App\Models\Pelanggan;
use App\Models\User;
use App\Models\Voyage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    private array $validStatusTransitions = [
        'menunggu' => ['diproses', 'ditolak', 'dibatalkan'],
        'diproses' => ['manifest', 'ditolak', 'dibatalkan'],
        'manifest' => ['siap_dimuat', 'ditolak', 'dibatalkan'],
        'siap_dimuat' => ['dimuat', 'ditolak'],
        'dimuat' => ['dalam_perjalanan'],
        'dalam_perjalanan' => ['diterima'],
        'diterima' => [],
        'ditolak' => [],
        'dibatalkan' => [],
    ];

    private array $statusUpdateRoles = ['admin', 'operator'];

    private function currentPelanggan()
    {
        $user = Auth::guard("api")->user();
        if (!$user || $user->role !== "pelanggan") {
            return null;
        }
        return Pelanggan::where("id_user", $user->id_user)->first();
    }

    private function canAccessBooking(Booking $booking): bool
    {
        $user = Auth::guard("api")->user();
        if (!$user) {
            return false;
        }
        if (in_array($user->role, ['super_admin', 'admin', 'operator', 'upp', 'checker', 'manajer', 'kantor_perhubungan'])) {
            return true;
        }
        if ($user->role === "pelanggan") {
            $pelanggan = $this->currentPelanggan();
            return $pelanggan && $booking->id_pelanggan === $pelanggan->id_pelanggan;
        }
        return false;
    }

    private function calculateEstimasiBiaya(array $barang, string $asal, string $tujuan): float
    {
        // Coba ambil tarif dari tabel master jika ada
        $tarifPerKg = 500; // Default fallback

        // Cek apakah ada tabel tarif dan ambil tarif yang sesuai
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('tarif')) {
                $tarif = \App\Models\Tarif::where('kategori_barang', $barang[0]['kategori'] ?? 'umum')
                    ->where('rute', 'LIKE', "%{$tujuan}%")
                    ->where('berlaku_dari', '<=', now())
                    ->where(function($q) {
                        $q->whereNull('berlaku_hingga')->orWhere('berlaku_hingga', '>=', now());
                    })
                    ->first();

                if ($tarif) {
                    $tarifPerKg = $tarif->tarif_per_kg ?? 500;
                }
            }
        } catch (\Exception $e) {
            // Jika terjadi error, gunakan default
        }

        $totalBerat = collect($barang)->sum(fn($item) => ($item['berat'] ?? 0) * ($item['jumlah_kemasan'] ?? 1));
        return $totalBerat * $tarifPerKg;
    }

    private function generateKodeBooking(): string
    {
        $tanggal = date("Ymd");
        $prefix = "BK-{$tanggal}-";
        $lastBooking = Booking::where('kode_booking', 'LIKE', $prefix . '%')
            ->orderBy('kode_booking', 'desc')->first();
        $newNum = $lastBooking ? ((int) substr($lastBooking->kode_booking, -5)) + 1 : 1;
        return $prefix . str_pad($newNum, 5, "0", STR_PAD_LEFT);
    }

    public function index(Request $request)
    {
        $user = Auth::guard("api")->user();
        $query = Booking::with(["pelanggan.perusahaan", "barang", "manifest"]);
        if ($request->status) $query->where("status", $request->status);
        if ($request->kode_booking) $query->where("kode_booking", "LIKE", "%" . $request->kode_booking . "%");
        if ($request->tanggal) $query->whereDate("tgl_booking", $request->tanggal);
        if ($user->role === "pelanggan") {
            $pelanggan = $this->currentPelanggan();
            if ($pelanggan) $query->where("id_pelanggan", $pelanggan->id_pelanggan);
            else $query->whereRaw("1 = 0");
        }
        $data = $query->orderBy("created_at", "desc")->paginate(20);
        return response()->json(["status" => "success", "data" => $data]);
    }

    public function store(Request $request)
    {
        $user = Auth::guard("api")->user();
        if (!$user || $user->role !== 'pelanggan') {
            return response()->json(["status" => "error", "message" => "Booking hanya dapat diajukan oleh akun pelanggan melalui aplikasi", "code" => "ROLE_BOOKING_CREATE"], 403);
        }

        $pelanggan = $this->currentPelanggan();
        if (!$pelanggan) {
            return response()->json(["status" => "error", "message" => "Profil pelanggan belum tersedia", "code" => "PELANGGAN_NOT_FOUND"], 403);
        }

        $validator = Validator::make($request->all(), [
            "asal" => "required|string|max:100",
            "tujuan" => "required|string|max:100",
            "tgl_booking" => "required|date|after_or_equal:today",
            "id_pelanggan" => "prohibited",
            "barang" => "required|array|min:1",
            "barang.*.nama_barang" => "required|string|max:255",
            "barang.*.kategori" => "required|in:umum,berbahaya,bahan_pokok,lainnya",
            "barang.*.berat" => "required|numeric|min:0.01",
            "barang.*.jumlah_kemasan" => "required|integer|min:1",
            "barang.*.volume" => "nullable|numeric|min:0",
            "barang.*.keterangan" => "nullable|string|max:500",
        ]);
        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal", "errors" => $validator->errors(), "code" => "VALIDATION_ERROR"], 422);
        }
        $idPelanggan = $pelanggan->id_pelanggan;
        $pelangganData = Pelanggan::find($idPelanggan);
        if (!$pelangganData) {
            return response()->json(["status" => "error", "message" => "Pelanggan tidak ditemukan", "code" => "PELANGGAN_INVALID"], 422);
        }
        DB::beginTransaction();
        try {
            $kodeBooking = $this->generateKodeBooking();
            $totalBerat = collect($request->barang)->sum(fn($item) => $item['berat'] * $item['jumlah_kemasan']);
            $estimasiBiaya = $this->calculateEstimasiBiaya($request->barang, $request->asal, $request->tujuan);
            $booking = Booking::create([
                "kode_booking" => $kodeBooking, "id_pelanggan" => $idPelanggan, "tgl_booking" => $request->tgl_booking,
                "asal" => $request->asal, "tujuan" => $request->tujuan, "catatan" => $request->catatan,
                "status" => "menunggu", "estimasi_biaya" => $estimasiBiaya,
            ]);
            foreach ($request->barang as $item) {
                Barang::create([
                    "id_booking" => $booking->id_booking, "nama_barang" => $item["nama_barang"],
                    "kategori" => $item["kategori"], "berat" => $item["berat"], "volume" => $item["volume"] ?? 0,
                    "jumlah_kemasan" => $item["jumlah_kemasan"], "dokumen_izin" => $item["dokumen_izin"] ?? null,
                    "status_muatan" => "belum_dimuat", "keterangan" => $item["keterangan"] ?? null,
                ]);
            }
            $operators = User::where("role", "operator")->where("is_active", true)->get();
            foreach ($operators as $op) {
                Notifikasi::create([
                    "id_user" => $op->id_user, "judul" => "Booking Baru",
                    "pesan" => "Booking baru {$kodeBooking} dari {$pelangganData->nama} menunggu konfirmasi",
                    "tipe" => "info", "link" => "/bookings/{$booking->id_booking}", "waktu" => now(),
                ]);
            }
            DB::commit();
            return response()->json(["status" => "success", "message" => "Booking berhasil dibuat dengan kode {$kodeBooking}", "data" => $booking->load("barang", "pelanggan.perusahaan")], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(["status" => "error", "message" => "Gagal membuat booking: " . $e->getMessage(), "code" => "BOOKING_CREATE_ERROR"], 500);
        }
    }

    public function show($id)
    {
        $booking = Booking::with(["pelanggan.perusahaan", "barang", "manifest.detail.realisasi"])->find($id);
        if (!$booking) {
            return response()->json(["status" => "error", "message" => "Booking tidak ditemukan", "code" => "BOOKING_NOT_FOUND"], 404);
        }
        if (!$this->canAccessBooking($booking)) {
            return response()->json(["status" => "error", "message" => "Booking tidak dapat diakses", "code" => "BOOKING_ACCESS_DENIED"], 403);
        }
        return response()->json(["status" => "success", "data" => $booking]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::guard("api")->user();
        if (!in_array($user->role, ["admin", "operator"], true)) {
            return response()->json(["status" => "error", "message" => "Role tidak diizinkan mengubah booking", "code" => "ROLE_BOOKING_UPDATE"], 403);
        }
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(["status" => "error", "message" => "Booking tidak ditemukan", "code" => "BOOKING_NOT_FOUND"], 404);
        }
        if (in_array($booking->status, ['diterima', 'ditolak', 'dibatalkan'])) {
            return response()->json(["status" => "error", "message" => "Booking sudah final dan tidak dapat diubah", "code" => "BOOKING_FINAL_STATE"], 422);
        }
        $booking->update($request->only(['asal', 'tujuan', 'catatan']));
        return response()->json(["status" => "success", "message" => "Booking berhasil diupdate", "data" => $booking]);
    }

    public function destroy($id)
    {
        return response()->json([
            "status" => "error",
            "message" => "Booking tidak dihapus agar riwayat operasional tetap utuh. Gunakan status pembatalan bila diperlukan.",
            "code" => "BOOKING_DELETE_DISABLED",
        ], 403);
    }

    public function tracking($kode)
    {
        $booking = Booking::with(["barang", "manifest.voyage"])
            ->where("kode_booking", $kode)->first();
        if (!$booking) {
            return response()->json(["status" => "error", "message" => "Kode booking tidak ditemukan", "code" => "TRACKING_NOT_FOUND"], 404);
        }

        // Hitung posisi real-time berdasarkan data voyage + status
        $position = $this->computeCurrentPosition($booking);

        return response()->json([
            "status" => "success",
            "data" => [
                "booking" => [
                    "kode_booking" => $booking->kode_booking,
                    "tgl_booking" => $booking->tgl_booking?->toDateString(),
                    "asal" => $booking->asal,
                    "tujuan" => $booking->tujuan,
                    "barang" => $booking->barang->map(fn (Barang $barang): array => $barang->only([
                        'nama_barang', 'kategori', 'status_muatan',
                    ])),
                ],
                "current_status" => $booking->status,
                "status_label" => $this->getStatusLabel($booking->status),
                "position" => $position,
            ],
        ]);
    }

    /**
     * Hitung posisi real-time barang di peta berdasarkan data voyage aktual.
     *
     * Logika:
     * - jika status < 'siap_dimuat'       -> posisi di pelabuhan asal (lat_asal, lng_asal)
     * - jika 'siap_dimuat'                -> posisi di pelabuhan transit (lat_transit, lng_transit)
     * - jika 'dimuat'/'dalam_perjalanan' -> posisi interpolasi antara pelabuhan asal/tujuan
     *                                     berdasarkan (now - waktu_keberangkatan) / estimasi_jam
     * - jika 'diterima'                   -> posisi di pelabuhan tujuan (lat_tujuan, lng_tujuan)
     */
    private function computeCurrentPosition(Booking $booking): array
    {
        $voyage = $booking->manifest?->voyage;
        $fallback = [
            "lat" => -7.2575,
            "lng" => 112.7521,
            "label" => "Pelabuhan Asal (Surabaya)",
            "status" => "unknown",
        ];

        if (!$voyage || !$voyage->estimasi_jam) {
            // Tidak ada voyage terlampir — gunakan fallback berdasarkan status
            $defaultByStatus = [
                "menunggu"           => ["lat" => -7.2575, "lng" => 112.7521, "label" => "Menunggu di " . ($voyage?->pelabuhan_asal ?? "Surabaya")],
                "diproses"           => ["lat" => -7.2575, "lng" => 112.7521, "label" => "Diproses di " . ($voyage?->pelabuhan_asal ?? "Surabaya")],
                "manifest"           => ["lat" => -7.2575, "lng" => 112.7521, "label" => "Manifest dibuat di " . ($voyage?->pelabuhan_asal ?? "Surabaya")],
                "siap_dimuat"        => ["lat" => -5.1477, "lng" => 119.4327, "label" => "Siap dimuat di " . ($voyage?->pelabuhan_transit ?? "Makassar")],
                "dimuat"             => ["lat" => -5.1477, "lng" => 119.4327, "label" => "Sudah dimuat di " . ($voyage?->pelabuhan_transit ?? "Makassar")],
                "dalam_perjalanan"   => ["lat" => -3.5952, "lng" => 125.5570, "label" => "Dalam perjalanan"],
                "diterima"           => ["lat" => 1.7392, "lng" => 125.0651, "label" => "Tiba di " . ($voyage?->pelabuhan_tujuan ?? "Tahuna")],
            ];
            $pos = $defaultByStatus[$booking->status] ?? null;
            if (!$pos) return $fallback;
            return [
                "lat" => $pos["lat"],
                "lng" => $pos["lng"],
                "label" => $pos["label"],
                "status" => $booking->status,
                "progress_pct" => 0,
                "eta" => null,
                "estimasi_jam" => null,
            ];
        }

        // Voyage ada, hitung berdasarkan waktu aktual
        $estimasiJam = (int) $voyage->estimasi_jam;
        $waktuBerangkat = $voyage->waktu_keberangkatan ?? null;

        // Posisi awal & akhir (asal -> tujuan, dengan transit kalau ada)
        $latAwal = $voyage->lat_asal ?? -7.2575;
        $lngAwal = $voyage->lng_asal ?? 112.7521;
        $latTujuan = $voyage->lat_tujuan ?? 1.7392;
        $lngTujuan = $voyage->lng_tujuan ?? 125.0651;
        $latTransit = $voyage->lat_transit;
        $lngTransit = $voyage->lng_transit;

        $labelAsal = $voyage->pelabuhan_asal ?? "Pelabuhan Asal";
        $labelTransit = $voyage->pelabuhan_transit ?? "Pelabuhan Transit";
        $labelTujuan = $voyage->pelabuhan_tujuan ?? "Pelabuhan Tujuan";

        $now = now();
        $progressPct = 0;
        $eta = null;

        // Hitung ETA = waktu_keberangkatan + estimasi_jam (kalau sudah berangkat)
        if ($waktuBerangkat && $estimasiJam > 0) {
            $etaDt = \Carbon\Carbon::parse($waktuBerangkat)->addHours($estimasiJam);
            $eta = $etaDt->toIso8601String();
        }

        if (in_array($booking->status, ['menunggu', 'diproses', 'manifest'])) {
            $lat = $latAwal; $lng = $lngAwal;
            $label = "Belum berangkat - " . $labelAsal;
            $progressPct = 0;
        } elseif ($booking->status === 'siap_dimuat') {
            // Barang sudah sampai pelabuhan transit & menunggu dimuat
            if ($latTransit) {
                $lat = $latTransit; $lng = $lngTransit;
            } else {
                $lat = $latAwal; $lng = $lngAwal;
            }
            $label = "Siap dimuat di " . ($latTransit ? $labelTransit : $labelAsal);
            $progressPct = $latTransit ? 20 : 0;
        } elseif ($booking->status === 'dimuat' || $booking->status === 'dalam_perjalanan') {
            // Interpolasi linear berdasarkan (now - waktu_keberangkatan) / estimasi_jam
            if ($waktuBerangkat && $estimasiJam > 0) {
                $depart = \Carbon\Carbon::parse($waktuBerangkat);
                $totalSeconds = $estimasiJam * 3600;
                $elapsedSeconds = max(0, $depart->diffInSeconds($now, false));
                $progressPct = min(100, max(0, ($elapsedSeconds / $totalSeconds) * 100));

                // Path: asal -> (transit ->) tujuan. Kalau ada transit, pecah jadi 2 segmen.
                if ($latTransit && $lngTransit && $progressPct < 50) {
                    // Segmen 1: asal -> transit
                    $segProgress = $progressPct / 50; // 0..1
                    $lat = $latAwal + ($latTransit - $latAwal) * $segProgress;
                    $lng = $lngAwal + ($lngTransit - $lngAwal) * $segProgress;
                    $label = "Berlayar ke " . $labelTransit;
                } else {
                    // Segmen 2: transit -> tujuan (atau langsung kalau tidak ada transit)
                    if ($latTransit && $lngTransit) {
                        $segProgress = ($progressPct - 50) / 50;
                        $lat = $latTransit + ($latTujuan - $latTransit) * max(0, $segProgress);
                        $lng = $lngTransit + ($lngTujuan - $lngTransit) * max(0, $segProgress);
                    } else {
                        $segProgress = $progressPct / 100;
                        $lat = $latAwal + ($latTujuan - $latAwal) * $segProgress;
                        $lng = $lngAwal + ($lngTujuan - $lngAwal) * $segProgress;
                    }
                    $label = $progressPct >= 99 ? "Mendekati " . $labelTujuan : "Berlayar ke " . $labelTujuan;
                }
            } else {
                // Belum ada waktu_keberangkatan tercatat, pakai fallback
                $lat = $latTransit ?? $latAwal;
                $lng = $lngTransit ?? $lngAwal;
                $label = "Siap berlayar";
                $progressPct = 20;
            }
        } elseif ($booking->status === 'diterima') {
            $lat = $latTujuan;
            $lng = $lngTujuan;
            $label = "Tiba di " . $labelTujuan;
            $progressPct = 100;
        } elseif ($booking->status === 'ditolak') {
            return [
                "lat" => $latAwal,
                "lng" => $lngAwal,
                "label" => "Ditolak di " . $labelAsal,
                "status" => $booking->status,
                "progress_pct" => 0,
                "eta" => null,
                "estimasi_jam" => $estimasiJam,
            ];
        } elseif ($booking->status === 'dibatalkan') {
            return [
                "lat" => $latAwal,
                "lng" => $lngAwal,
                "label" => "Dibatalkan di " . $labelAsal,
                "status" => $booking->status,
                "progress_pct" => 0,
                "eta" => null,
                "estimasi_jam" => $estimasiJam,
            ];
        } else {
            $lat = $latAwal; $lng = $lngAwal;
            $label = "Status tidak dikenali";
            $progressPct = 0;
        }

        return [
            "lat" => round((float)$lat, 6),
            "lng" => round((float)$lng, 6),
            "label" => $label,
            "status" => $booking->status,
            "progress_pct" => round($progressPct, 2),
            "eta" => $eta,
            "estimasi_jam" => $estimasiJam,
            "waktu_keberangkatan" => $waktuBerangkat ? (\Carbon\Carbon::parse($waktuBerangkat))->toIso8601String() : null,
        ];
    }

    private function getStatusLabel(string $status): string
    {
        $labels = ['menunggu' => 'Menunggu Konfirmasi', 'diproses' => 'Sedang Diproses', 'manifest' => 'Manifest Dibuat',
            'siap_dimuat' => 'Siap Dimuat', 'dimuat' => 'Sudah Dimuat', 'dalam_perjalanan' => 'Dalam Perjalanan',
            'diterima' => 'Tiba di Tujuan', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan'];
        return $labels[$status] ?? $status;
    }

    public function updateStatus(Request $request, $id)
    {
        $user = Auth::guard("api")->user();
        \Log::info("updateStatus called", [
            'user_id' => $user?->id_user,
            'user_role' => $user?->role,
            'booking_id' => $id,
            'requested_status' => $request->status,
            'roles_allowed' => $this->statusUpdateRoles,
        ]);
        if (!in_array($user->role, $this->statusUpdateRoles)) {
            return response()->json(["status" => "error", "message" => "Role \"{$user->role}\" tidak diizinkan mengubah status", "code" => "ROLE_STATUS_UPDATE"], 403);
        }
        $validator = Validator::make($request->all(), [
            "status" => "required|in:menunggu,diproses,manifest,siap_dimuat,dimuat,dalam_perjalanan,diterima,ditolak,dibatalkan",
            "catatan" => "nullable|string|max:500",
        ]);
        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal: " . implode(', ', $validator->errors()->all()), "errors" => $validator->errors(), "code" => "VALIDATION_ERROR"], 422);
        }
        $booking = Booking::with('manifest')->find($id);
        if (!$booking) {
            return response()->json(["status" => "error", "message" => "Booking tidak ditemukan", "code" => "BOOKING_NOT_FOUND"], 404);
        }
        $newStatus = $request->status;
        $currentStatus = $booking->status;
        \Log::info("Status transition check", [
            'current_status' => $currentStatus,
            'new_status' => $newStatus,
            'allowed' => $this->validStatusTransitions[$currentStatus] ?? [],
        ]);
        if (!isset($this->validStatusTransitions[$currentStatus]) || !in_array($newStatus, $this->validStatusTransitions[$currentStatus])) {
            return response()->json(["status" => "error", "message" => "Transisi status tidak valid. Status saat ini: \"{$currentStatus}\", target: \"{$newStatus}\"", "code" => "INVALID_STATUS_TRANSITION", "allowed_transitions" => $this->validStatusTransitions[$currentStatus] ?? []], 422);
        }
        if ($newStatus === 'siap_dimuat' && (!$booking->manifest || $booking->manifest->status !== 'disetujui')) {
            return response()->json(["status" => "error", "message" => "Manifest harus disetujui UPP terlebih dahulu", "code" => "MANIFEST_NOT_APPROVED"], 422);
        }
        if (in_array($newStatus, ['ditolak', 'dibatalkan']) && !$request->catatan) {
            return response()->json(["status" => "error", "message" => "Harus memberikan alasan", "code" => "REASON_REQUIRED"], 422);
        }
        DB::beginTransaction();
        try {
            $booking->update(["status" => $newStatus, "catatan" => $request->catatan ?? $booking->catatan]);
            \Log::info("Booking status updated", ['booking_id' => $booking->id_booking, 'new_status' => $newStatus]);
            if ($booking->pelanggan && $booking->pelanggan->id_user) {
                Notifikasi::create([
                    "id_user" => $booking->pelanggan->id_user, "judul" => "Update Status Booking",
                    "pesan" => "Status booking {$booking->kode_booking} berubah menjadi: {$this->getStatusLabel($newStatus)}",
                    "tipe" => in_array($newStatus, ['ditolak', 'dibatalkan']) ? "error" : "info", "waktu" => now(),
                ]);
                \Log::info("Notification created for customer");
            }
            DB::commit();
            return response()->json(["status" => "success", "message" => "Status berhasil diupdate", "data" => $booking->fresh()]);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error("Status update failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(["status" => "error", "message" => "Gagal update status: " . $e->getMessage(), "code" => "STATUS_UPDATE_ERROR"], 500);
        }
    }

    public function getAvailableTransitions($id)
    {
        $user = Auth::guard("api")->user();
        if (!in_array($user->role, $this->statusUpdateRoles)) {
            return response()->json(["status" => "error", "message" => "Anda tidak memiliki izin", "code" => "ROLE_DENIED"], 403);
        }
        $booking = Booking::find($id);
        if (!$booking) {
            return response()->json(["status" => "error", "message" => "Booking tidak ditemukan", "code" => "BOOKING_NOT_FOUND"], 404);
        }
        return response()->json(["status" => "success", "data" => ["current_status" => $booking->status, "available_transitions" => array_map(fn($s) => ["status" => $s, "label" => $this->getStatusLabel($s), "requires_note" => in_array($s, ['ditolak', 'dibatalkan'])], $this->validStatusTransitions[$booking->status] ?? [])]]);
    }
}
