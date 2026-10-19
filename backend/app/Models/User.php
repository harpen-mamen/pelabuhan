<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, FilamentUser, HasName
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nama', 'username', 'email', 'kontak', 'password', 'role', 'is_active',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    // Filament User Interface
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        // Web admin panel hanya untuk admin utama. Role operasional memakai aplikasi mobile.
        return $this->is_active && $this->role === 'super_admin';
    }

    public function getFilamentName(): string
    {
        return (string) ($this->nama ?: $this->username ?: $this->email ?: 'Admin');
    }
    
    public function canAccessTenant(\Filament\Models\Contracts\Tenant $tenant): bool
    {
        return true;
    }

    // JWT Interface
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'nama' => $this->nama,
        ];
    }

    // Relationships
    public function pelanggan(): HasOne
    {
        return $this->hasOne(Pelanggan::class, 'id_user', 'id_user');
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'id_user', 'id_user');
    }

    public function manifest(): HasMany
    {
        return $this->hasMany(Manifest::class, 'id_user_pembuat', 'id_user');
    }

    public function hasRole($role)
    {
        return $this->role === $role;
    }
}
