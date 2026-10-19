<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TarifController extends Controller
{
    private array $createRoles = ['super_admin', 'admin'];
    private array $updateRoles = ['super_admin', 'admin'];
    private array $readRoles = ['super_admin', 'admin', 'operator', 'upp', 'pelanggan'];

    public function index(Request $request)
    {
        $user = Auth::guard("api")->user();

        if (!in_array($user->role, $this->readRoles)) {
            return response()->json([
                "status" => "error",
                "message" => "Role tidak diizinkan melihat tarif",
                "code" => "ROLE_DENIED"
            ], 403);
        }

        $query = Tarif::query();

        if ($request->kategori) {
            $query->where('kategori_barang', $request->kategori);
        }

        if ($request->rute) {
            $query->where('rute', 'LIKE', "%{$request->rute}%");
        }

        if (!$request->has('semua')) {
            $query->where('berlaku_dari', '<=', now())
                ->where(function($q) {
                    $q->whereNull('berlaku_hingga')->orWhere('berlaku_hingga', '>=', now());
                });
        }

        $data = $query->orderBy('kategori_barang')->orderBy('rute')->paginate(20);

        return response()->json(["status" => "success", "data" => $data]);
    }

    public function store(Request $request)
    {
        $user = Auth::guard("api")->user();

        if (!in_array($user->role, $this->createRoles)) {
            return response()->json([
                "status" => "error",
                "message" => "Hanya admin yang dapat membuat tarif",
                "code" => "ROLE_DENIED"
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'jenis_layanan' => 'required|string|max:100',
            'kategori_barang' => 'required|in:umum,berbahaya,bahan_pokok,b3,lainnya',
            'rute' => 'required|string|max:255',
            'tarif_per_kg' => 'required|numeric|min:0',
            'tarif_per_m3' => 'nullable|numeric|min:0',
            'berlaku_dari' => 'required|date',
            'berlaku_hingga' => 'nullable|date|after_or_equal:berlaku_dari',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => "Validasi gagal",
                "errors" => $validator->errors(),
                "code" => "VALIDATION_ERROR"
            ], 422);
        }

        $tarif = Tarif::create($request->only([
            'jenis_layanan', 'kategori_barang', 'rute',
            'tarif_per_kg', 'tarif_per_m3', 'berlaku_dari', 'berlaku_hingga'
        ]));

        return response()->json([
            "status" => "success",
            "message" => "Tarif berhasil dibuat",
            "data" => $tarif
        ], 201);
    }

    public function hitung(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kategori' => 'required|in:umum,berbahaya,bahan_pokok,b3,lainnya',
            'tujuan' => 'required|string',
            'berat_kg' => 'required|numeric|min:0',
            'volume_m3' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => "Validasi gagal",
                "errors" => $validator->errors(),
                "code" => "VALIDATION_ERROR"
            ], 422);
        }

        $tarif = Tarif::where('kategori_barang', $request->kategori)
            ->where('rute', 'LIKE', "%{$request->tujuan}%")
            ->where('berlaku_dari', '<=', now())
            ->where(function($q) {
                $q->whereNull('berlaku_hingga')->orWhere('berlaku_hingga', '>=', now());
            })
            ->first();

        $tarifPerKg = $tarif->tarif_per_kg ?? 500;
        $tarifPerM3 = $tarif->tarif_per_m3 ?? 1000;

        $biayaBerat = $request->berat_kg * $tarifPerKg;
        $biayaVolume = ($request->volume_m3 ?? 0) * $tarifPerM3;
        $totalBiaya = max($biayaBerat, $biayaVolume);

        return response()->json([
            "status" => "success",
            "data" => [
                "tarif_per_kg" => $tarifPerKg,
                "tarif_per_m3" => $tarifPerM3,
                "biaya_berat" => $biayaBerat,
                "biaya_volume" => $biayaVolume,
                "total_estimasi" => $totalBiaya,
                "sumber_tarif" => $tarif ? "master_tarif" : "default"
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::guard("api")->user();

        if (!in_array($user->role, $this->updateRoles, true)) {
            return response()->json([
                "status" => "error",
                "message" => "Hanya admin yang dapat mengubah tarif",
                "code" => "ROLE_DENIED"
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'jenis_layanan' => 'required|string|max:100',
            'kategori_barang' => 'required|in:umum,berbahaya,bahan_pokok,b3,lainnya',
            'rute' => 'required|string|max:255',
            'tarif_per_kg' => 'required|numeric|min:0',
            'tarif_per_m3' => 'nullable|numeric|min:0',
            'berlaku_dari' => 'required|date',
            'berlaku_hingga' => 'nullable|date|after_or_equal:berlaku_dari',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => "Validasi gagal",
                "errors" => $validator->errors(),
                "code" => "VALIDATION_ERROR"
            ], 422);
        }

        $tarif = Tarif::findOrFail($id);
        $tarif->update($request->only([
            'jenis_layanan', 'kategori_barang', 'rute',
            'tarif_per_kg', 'tarif_per_m3', 'berlaku_dari', 'berlaku_hingga'
        ]));

        return response()->json([
            "status" => "success",
            "message" => "Tarif berhasil diubah",
            "data" => $tarif->fresh()
        ]);
    }
}
