<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Definisi Permission per Role
     * Format: role => [permissions]
     */
    private array $rolePermissions = [
        'super_admin' => [
            // Admin utama memakai web panel untuk pemantauan dan tata kelola sistem.
            '*',
            // Audit
            'audit.read', 'audit.export',
        ],
        'admin' => [
            'booking.read', 'booking.update', 'booking.status',
            'manifest.create', 'manifest.read', 'manifest.update', 'manifest.submit',
            'voyage.create', 'voyage.read', 'voyage.update', 'voyage.status', 'voyage.delete',
            'kapal.create', 'kapal.read', 'kapal.update', 'kapal.delete',
            'pelanggan.create', 'pelanggan.read', 'pelanggan.update', 'pelanggan.delete',
            'perusahaan.create', 'perusahaan.read', 'perusahaan.update', 'perusahaan.delete',
            'realisasi.create', 'realisasi.read', 'realisasi.selisih',
            'laporan.view', 'laporan.generate', 'laporan.export', 'laporan.kepatuhan', 'laporan.pnbp',
            'dashboard.view', 'dashboard.full', 'dashboard.trend',
            'users.read', 'users.create', 'users.update', 'users.deactivate',
            'tarif.read', 'tarif.manage', 'audit.read',
        ],
        'operator' => [
            'booking.read', 'booking.update', 'booking.status',
            'manifest.create', 'manifest.read', 'manifest.update', 'manifest.submit',
            'voyage.read', 'voyage.create', 'voyage.update', 'voyage.status',
            'kapal.read', 'pelanggan.read', 'perusahaan.read', 'tarif.read',
            'realisasi.read', 'realisasi.selisih',
            'dashboard.view',
            'laporan.view', 'laporan.generate',
        ],
        'upp' => [
            'manifest.verifikasi', 'manifest.read', 'realisasi.read', 'realisasi.selisih',
            'kapal.read', 'voyage.read', 'tarif.read',
            'laporan.view', 'laporan.generate', 'laporan.export', 'laporan.kepatuhan', 'laporan.pnbp',
            'dashboard.view',
            'audit.read',
        ],
        'checker' => [
            'manifest.read', 'voyage.read', 'kapal.read',
            'realisasi.create', 'realisasi.read', 'realisasi.statistics',
            'dashboard.view',
        ],
        'pelanggan' => [
            'booking.create', 'booking.read', 'booking.tracking', 'voyage.read', 'tarif.read',
            'pelanggan.read',
            'dashboard.view',
        ],
        'manajer' => [
            'booking.read', 'manifest.read', 'voyage.read', 'kapal.read', 'realisasi.read',
            'laporan.view', 'laporan.generate', 'laporan.export',
            'laporan.kepatuhan', 'laporan.pnbp', 'dashboard.view', 'dashboard.full', 'dashboard.trend',
            'audit.read',
        ],
        'kantor_perhubungan' => [
            'laporan.view', 'laporan.generate', 'laporan.export', 'laporan.kepatuhan', 'laporan.pnbp',
            'dashboard.view', 'dashboard.full', 'dashboard.trend',
            'audit.read',
            'manifest.read', 'booking.read', 'voyage.read', 'kapal.read',
        ],
    ];

    /**
     * Explicit denials take precedence over wildcard permissions. The primary
     * admin monitors bookings; customers submit them and operations staff process them.
     */
    private array $roleDeniedPermissions = [
        'super_admin' => ['booking.create', 'booking.update', 'booking.delete', 'booking.status'],
        'admin' => ['booking.create', 'booking.delete'],
        'operator' => ['booking.create', 'booking.delete'],
        'pelanggan' => ['booking.update', 'booking.delete', 'booking.status'],
    ];

    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "Unauthenticated - Silakan login terlebih dahulu",
                "code" => "AUTH_001"
            ], 401);
        }

        // Cek user active
        if (!$user->is_active) {
            return response()->json([
                "status" => "error",
                "message" => "Akun nonaktif - Hubungi administrator",
                "code" => "AUTH_002"
            ], 403);
        }

        // Cek role yang diizinkan. super_admin otomatis punya akses ke semua route
        // yang dilindungi middleware role apapun, TETAPI controller dapat menolak
        // (misal update/delete super_admin lain) lewat pengecekannya sendiri.
        $roleList = implode(', ', $roles);
        if ($user->role !== 'super_admin' && !in_array($user->role, $roles)) {
            return response()->json([
                "status" => "error",
                "message" => "Akses ditolak - Role \"{$user->role}\" tidak memiliki izin untuk resource ini. Required: {$roleList}",
                "code" => "ROLE_001",
                "required_roles" => $roles
            ], 403);
        }

        // Simpan permissions ke request untuk validasi granular
        $request->merge([
            '_user_role' => $user->role,
            '_user_permissions' => $this->getPermissions($user->role),
        ]);

        return $next($request);
    }

    /**
     * Get all permissions for a role
     */
    public function getPermissions(string $role): array
    {
        return $this->rolePermissions[$role] ?? [];
    }

    /**
     * Check if role has specific permission
     */
    public static function hasPermission(string $role, string $permission): bool
    {
        $instance = new self();
        $permissions = $instance->getPermissions($role);

        if (in_array($permission, $instance->roleDeniedPermissions[$role] ?? [], true)) {
            return false;
        }

        // Wildcard access
        if (in_array('*', $permissions)) {
            return true;
        }

        return in_array($permission, $permissions);
    }

    /**
     * Get all roles with their permissions (for documentation)
     */
    public static function getRolePermissionsMap(): array
    {
        $instance = new self();
        $descriptions = [
            'super_admin' => 'Admin utama - Pemantauan, tata kelola akun, master data, dan audit melalui web. Booking hanya baca.',
            'admin' => 'Admin operasional - Mengelola data operasional dan memproses booking melalui aplikasi mobile.',
            'operator' => 'Operator - Memproses booking, manifest, dan voyage melalui aplikasi mobile.',
            'upp' => 'Syahbandar - Verifikasi & validasi manifest, audit',
            'checker' => 'Checker dermaga - Input realisasi bongkar/muat',
            'pelanggan' => 'Pelanggan - Membuat booking & tracking pengiriman',
            'manajer' => 'Manajer - Laporan & analytics, audit',
            'kantor_perhubungan' => 'Kantor Perhubungan - Oversight lintas perusahaan, PNBP, audit',
        ];

        $result = [];
        foreach ($instance->rolePermissions as $role => $permissions) {
            $result[$role] = [
                'description' => $descriptions[$role] ?? 'Unknown role',
                'permissions' => $permissions,
                'denied_permissions' => $instance->roleDeniedPermissions[$role] ?? [],
            ];
        }

        return $result;
    }
}
