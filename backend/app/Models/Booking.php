<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $table = 'booking';
    protected $primaryKey = 'id_booking';

    protected $fillable = ['kode_booking', 'id_pelanggan', 'tgl_booking', 'asal', 'tujuan', 'catatan', 'status', 'estimasi_biaya'];

    protected $casts = [
        'tgl_booking' => 'date',
        'estimasi_biaya' => 'decimal:2',
    ];

    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }

    public function barang()
    {
        return $this->hasMany(Barang::class, 'id_booking', 'id_booking');
    }

    public function manifest()
    {
        return $this->hasOne(Manifest::class, 'id_booking', 'id_booking');
    }
}
