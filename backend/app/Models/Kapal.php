<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kapal extends Model
{
    use HasFactory;

    protected $table = 'kapal';
    protected $primaryKey = 'id_kapal';

    protected $fillable = ['nama_kapal', 'kapasitas_dwt', 'jenis_kapal', 'nomor_imo', 'pemilik'];

    public function voyage(): HasMany
    {
        return $this->hasMany(Voyage::class, 'id_kapal', 'id_kapal');
    }
}
