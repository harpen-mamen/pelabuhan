<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Perusahaan extends Model
{
    use HasFactory;

    protected $table = 'perusahaan';
    protected $primaryKey = 'id_perusahaan';

    protected $fillable = ['nama_perusahaan', 'alamat', 'npwp', 'kontak'];

    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class, 'id_perusahaan', 'id_perusahaan');
    }
}
