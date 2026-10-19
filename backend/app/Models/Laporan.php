<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Laporan extends Model
{
    use HasFactory;

    protected $table = 'laporan';
    protected $primaryKey = 'id_laporan';

    protected $fillable = ['jenis_laporan', 'periode_awal', 'periode_akhir', 'id_user_generate', 'file_path', 'parameter'];

    protected $casts = [
        'periode_awal' => 'date',
        'periode_akhir' => 'date',
        'parameter' => 'array',
    ];

    public function generator()
    {
        return $this->belongsTo(User::class, 'id_user_generate', 'id_user');
    }
}
