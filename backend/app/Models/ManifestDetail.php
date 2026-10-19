<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManifestDetail extends Model
{
    use HasFactory;

    protected $table = 'manifest_detail';
    protected $primaryKey = 'id_manifest_detail';

    protected $fillable = ['id_manifest', 'id_barang', 'jumlah_manifest'];

    public function manifest()
    {
        return $this->belongsTo(Manifest::class, 'id_manifest', 'id_manifest');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'id_barang', 'id_barang');
    }

    public function realisasi()
    {
        return $this->hasOne(RealisasiBongkarMuat::class, 'id_manifest_detail', 'id_manifest_detail');
    }
}
