<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kapal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KapalController extends Controller
{
    public function index()
    {
        $data = Kapal::orderBy("id_kapal", "desc")->paginate(20);
        return response()->json(["status" => "success", "data" => $data]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "nama_kapal" => "required|string|max:255",
            "kapasitas_dwt" => "required|numeric|min:0",
            "jenis_kapal" => "nullable|string",
            "nomor_imo" => "nullable|string",
            "pemilik" => "nullable|string",
        ]);

        if ($validator->fails()) {
            return response()->json(["status" => "error", "message" => "Validasi gagal", "data" => $validator->errors()], 422);
        }

        $data = Kapal::create($request->all());
        return response()->json(["status" => "success", "message" => "Kapal berhasil ditambahkan", "data" => $data], 201);
    }

    public function show($id)
    {
        $data = Kapal::with("voyage")->findOrFail($id);
        return response()->json(["status" => "success", "data" => $data]);
    }

    public function update(Request $request, $id)
    {
        $data = Kapal::findOrFail($id);
        $data->update($request->all());
        return response()->json(["status" => "success", "message" => "Kapal berhasil diupdate", "data" => $data]);
    }

    public function destroy($id)
    {
        $data = Kapal::findOrFail($id);
        $data->delete();
        return response()->json(["status" => "success", "message" => "Kapal berhasil dihapus"]);
    }
}
