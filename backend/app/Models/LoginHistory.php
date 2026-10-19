<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    use HasFactory;

    protected $table = 'login_history';
    protected $primaryKey = 'id_login';

    protected $fillable = [
        'id_user',
        'username',
        'status',
        'reason',
        'ip_address',
        'user_agent',
        'token_id',
        'login_at',
        'logout_at',
        'session_duration',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    // Scope for successful logins
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    // Scope for failed logins
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Scope for specific user
    public function scopeForUser($query, $userId)
    {
        return $query->where('id_user', $userId);
    }

    // Scope for date range
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('login_at', [$start, $end]);
    }
}
