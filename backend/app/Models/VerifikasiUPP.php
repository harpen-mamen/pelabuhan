<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerifikasiUPP extends Model
{
    use HasFactory;

    protected $table = 'verifikasi_upp';
    protected $primaryKey = 'id_verifikasi';

    protected $fillable = ['id_manifest', 'id_petugas_upp', 'status_verifikasi', 'catatan', 'tgl_verifikasi'];

    protected $casts = [
        'tgl_verifikasi' => 'datetime',
    ];

    public function manifest()
    {
        return $this->belongsTo(Manifest::class, 'id_manifest', 'id_manifest');
    }

    public function petugasUpp()
    {
        return $this->belongsTo(User::class, 'id_petugas_upp', 'id_user');
    }
}
