<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "periode_awal"  => "nullable|date",
            "periode_akhir" => "nullable|date|after_or_equal:periode_awal",
            "tabel"         => "nullable|string",
            "id_user"       => "nullable|integer",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status"  => "error",
                "message" => "Validasi gagal",
                "data"    => $validator->errors(),
            ], 422);
        }

        $q = AuditLog::with(["user:id_user,nama,role,username"])
            ->orderBy("waktu", "desc");

        if ($request->periode_awal && $request->periode_akhir) {
            $q->whereBetween("waktu", [
                $request->periode_awal . " 00:00:00",
                $request->periode_akhir . " 23:59:59",
            ]);
        }
        if ($request->tabel) {
            $q->where("tabel_terkait", $request->tabel);
        }
        if ($request->id_user) {
            $q->where("id_user", $request->id_user);
        }

        $data = $q->limit(500)->get();

        return response()->json([
            "status" => "success",
            "data"   => $data,
        ]);
    }
}
