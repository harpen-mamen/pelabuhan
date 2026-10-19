<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barang extends Model
{
    use HasFactory;

    protected $table = 'barang';
    protected $primaryKey = 'id_barang';

    protected $fillable = ['id_booking', 'nama_barang', 'kategori', 'berat', 'volume', 'jumlah_kemasan', 'satuan_berat', 'satuan_volume', 'dokumen_izin', 'status_muatan', 'keterangan'];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }

    public function manifestDetail()
    {
        return $this->hasMany(ManifestDetail::class, 'id_barang', 'id_barang');
    }
}
