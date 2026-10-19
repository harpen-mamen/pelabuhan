<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PerusahaanController extends Controller
{
    public function index()
    {
        $data = Perusahaan::orderBy("id_perusahaan", "desc")->paginate(20);
        return response()->json(["status" => "success", "data" => $data]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "nama_perusahaan" => "required|string|max:255",
            "alamat" => "nullable|string",
            "npwp" => "nullable|string|max:30",
            "kontak" => "nullable|string|max:50",
        ]);

        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal", "data" => $validator->errors()], 422);
        }

        $data = Perusahaan::create($request->only(["nama_perusahaan", "alamat", "npwp", "kontak"]));

        return response()->json([
            "status" => "success",
            "message" => "Perusahaan berhasil ditambahkan",
            "data" => $data,
        ], 201);
    }

    public function show($id)
    {
        $data = Perusahaan::findOrFail($id);
        return response()->json(["status" => "success", "data" => $data]);
    }

    public function update(Request $request, $id)
    {
        $data = Perusahaan::findOrFail($id);
        $data->update($request->only(["nama_perusahaan", "alamat", "npwp", "kontak"]));
        return response()->json(["status" => "success", "message" => "Perusahaan berhasil diupdate", "data" => $data]);
    }

    public function destroy($id)
    {
        $data = Perusahaan::findOrFail($id);
        $data->delete();
        return response()->json(["status" => "success", "message" => "Perusahaan berhasil dihapus"]);
    }
}
