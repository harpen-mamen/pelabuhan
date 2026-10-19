<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Manifest extends Model
{
    use HasFactory;

    protected $table = 'manifest';
    protected $primaryKey = 'id_manifest';

    protected $fillable = ['nomor_manifest', 'id_voyage', 'id_booking', 'id_user_pembuat', 'tgl_manifest', 'total_berat', 'total_volume', 'status', 'catatan'];

    protected $casts = [
        'tgl_manifest' => 'date',
    ];

    public function voyage()
    {
        return $this->belongsTo(Voyage::class, 'id_voyage', 'id_voyage');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'id_booking', 'id_booking');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'id_user_pembuat', 'id_user');
    }

    public function detail()
    {
        return $this->hasMany(ManifestDetail::class, 'id_manifest', 'id_manifest');
    }

    public function verifikasi()
    {
        return $this->hasOne(VerifikasiUPP::class, 'id_manifest', 'id_manifest');
    }
}
