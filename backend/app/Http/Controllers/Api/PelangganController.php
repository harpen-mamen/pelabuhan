<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PelangganController extends Controller
{
    public function index(Request $request)
    {
        $query = Pelanggan::with(["perusahaan", "user"]);

        $user = Auth::guard("api")->user();
        if ($user->role === "pelanggan") {
            $query->where("id_user", $user->id_user);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where("nama", "LIKE", "%" . $request->search . "%")
                    ->orWhere("kontak", "LIKE", "%" . $request->search . "%");
            });
        }

        $data = $query->orderBy("nama")->paginate(20);

        return response()->json(["status" => "success", "data" => $data]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "id_user" => "nullable|exists:users,id_user",
            "id_perusahaan" => "nullable|exists:perusahaan,id_perusahaan",
            "nama" => "required|string|max:255",
            "alamat" => "nullable|string",
            "kontak" => "nullable|string|max:50",
        ]);

        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal", "data" => $validator->errors()], 422);
        }

        $data = Pelanggan::create($request->only(["id_user", "id_perusahaan", "nama", "alamat", "kontak"]));

        return response()->json([
            "status" => "success",
            "message" => "Pelanggan berhasil ditambahkan",
            "data" => $data->load(["perusahaan", "user"]),
        ], 201);
    }

    public function show($id)
    {
        $user = Auth::guard("api")->user();
        $data = Pelanggan::with(["perusahaan", "user"])->findOrFail($id);

        if ($user->role === "pelanggan" && (int) $data->id_user !== (int) $user->id_user) {
            return response()->json([
                "status" => "error",
                "message" => "Pelanggan hanya dapat melihat profilnya sendiri.",
                "code" => "RESOURCE_FORBIDDEN",
            ], 403);
        }

        return response()->json(["status" => "success", "data" => $data]);
    }

    public function update(Request $request, $id)
    {
        $data = Pelanggan::findOrFail($id);
        $data->update($request->only(["id_user", "id_perusahaan", "nama", "alamat", "kontak"]));

        return response()->json([
            "status" => "success",
            "message" => "Pelanggan berhasil diupdate",
            "data" => $data->load(["perusahaan", "user"]),
        ]);
    }

    public function destroy($id)
    {
        Pelanggan::findOrFail($id)->delete();

        return response()->json(["status" => "success", "message" => "Pelanggan berhasil dihapus"]);
    }
}
