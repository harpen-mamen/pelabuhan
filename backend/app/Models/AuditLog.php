<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_log';
    protected $primaryKey = 'id_log';

    protected $fillable = ['id_user', 'aksi', 'tabel_terkait', 'id_data_terkait', 'data_lama', 'data_baru', 'ip_address', 'waktu'];

    protected $casts = [
        'data_lama' => 'array',
        'data_baru' => 'array',
        'waktu' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
