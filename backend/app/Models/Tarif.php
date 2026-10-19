<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tarif extends Model
{
    use HasFactory;

    protected $table = 'tarif';

    protected $primaryKey = 'id_tarif';

    protected $fillable = [
        'jenis_layanan',
        'kategori_barang',
        'rute',
        'tarif_per_kg',
        'tarif_per_m3',
        'berlaku_dari',
        'berlaku_hingga',
    ];

    protected $casts = [
        'tarif_per_kg' => 'float',
        'tarif_per_m3' => 'float',
        'berlaku_dari' => 'date',
        'berlaku_hingga' => 'date',
    ];

    /**
     * Scope untuk tarif yang masih berlaku
     */
    public function scopeBerlaku($query)
    {
        return $query->where('berlaku_dari', '<=', now())
            ->where(function($q) {
                $q->whereNull('berlaku_hingga')
                  ->orWhere('berlaku_hingga', '>=', now());
            });
    }

    /**
     * Scope untuk cari tarif berdasarkan rute
     */
    public function scopeUntukRute($query, string $rute)
    {
        return $query->where('rute', 'LIKE', "%{$rute}%");
    }

    /**
     * Scope untuk cari tarif berdasarkan kategori
     */
    public function scopeUntukKategori($query, string $kategori)
    {
        return $query->where('kategori_barang', $kategori);
    }
}
