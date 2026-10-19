<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voyage extends Model
{
    use HasFactory;

    protected $table = 'voyage';
    protected $primaryKey = 'id_voyage';

    protected $fillable = ['id_kapal', 'nomor_voyage', 'rute', 'tgl_berangkat', 'tgl_tiba', 'status'];

    protected $casts = [
        'tgl_berangkat' => 'date',
        'tgl_tiba' => 'date',
    ];

    public function kapal(): BelongsTo
    {
        return $this->belongsTo(Kapal::class, 'id_kapal', 'id_kapal');
    }

    public function manifest(): HasMany
    {
        return $this->hasMany(Manifest::class, 'id_voyage', 'id_voyage');
    }
}
