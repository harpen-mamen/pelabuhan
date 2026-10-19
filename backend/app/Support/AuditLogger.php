<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public static function log(
        string $aksi,
        ?string $tabel = null,
        ?int $idData = null,
        ?array $dataLama = null,
        ?array $dataBaru = null
    ): void {
        $user = auth('api')->user();
        $request = request();

        // Guard agar $user benar-benar model User (bukan Collection/null)
        $idUser = ($user instanceof \App\Models\User) ? $user->id_user : null;

        AuditLog::create([
            'id_user'       => $idUser,
            'aksi'          => $aksi,
            'tabel_terkait' => $tabel,
            'id_data_terkait' => $idData,
            'data_lama'     => $dataLama,
            'data_baru'     => $dataBaru,
            'ip_address'    => $request?->ip(),
            'waktu'         => now(),
        ]);
    }

    public static function snapshotDiff(Model $model, array $newData): array
    {
        $old = $model->getOriginal();
        $changed = [];
        foreach ($newData as $k => $v) {
            if (array_key_exists($k, $old) && $old[$k] != $v) {
                $changed[$k] = ['old' => $old[$k], 'new' => $v];
            }
        }
        return ['old' => $old, 'new' => $newData, 'changed' => $changed];
    }
}
