<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealisasiBongkarMuat extends Model
{
    use HasFactory;

    protected $table = 'realisasi_bongkar_muat';
    protected $primaryKey = 'id_realisasi';

    protected $fillable = ['id_manifest_detail', 'id_checker', 'jumlah_realisasi', 'jenis', 'keterangan_selisih', 'waktu_input'];

    protected $casts = [
        'waktu_input' => 'datetime',
    ];

    public function manifestDetail()
    {
        return $this->belongsTo(ManifestDetail::class, 'id_manifest_detail', 'id_manifest_detail');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'id_checker', 'id_user');
    }
}
