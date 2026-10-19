<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotifikasiController extends Controller
{
    public function index()
    {
        $user = Auth::guard("api")->user();
        $data = Notifikasi::where("id_user", $user->id_user)
            ->orderBy("waktu", "desc")
            ->paginate(20);

        $unread = Notifikasi::where("id_user", $user->id_user)
            ->where("status_baca", false)
            ->count();

        return response()->json([
            "status" => "success",
            "data" => [
                "notifikasi" => $data,
                "unread_count" => $unread,
            ],
        ]);
    }

    public function markAsRead($id)
    {
        $notif = Notifikasi::where("id_user", Auth::guard("api")->user()->id_user)
            ->findOrFail($id);
        $notif->update(["status_baca" => true]);
        return response()->json(["status" => "success", "message" => "Notifikasi ditandai sudah dibaca"]);
    }

    public function markAllRead()
    {
        Notifikasi::where("id_user", Auth::guard("api")->user()->id_user)
            ->where("status_baca", false)
            ->update(["status_baca" => true]);
        return response()->json(["status" => "success", "message" => "Semua notifikasi ditandai sudah dibaca"]);
    }
}
