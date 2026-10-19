<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Silakan login terlebih dahulu.',
                'code' => 'AUTH_001',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun nonaktif. Hubungi administrator.',
                'code' => 'AUTH_002',
            ], 403);
        }

        if (!RoleMiddleware::hasPermission($user->role, $permission)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role Anda tidak memiliki izin untuk tindakan ini.',
                'code' => 'PERMISSION_001',
                'required_permission' => $permission,
            ], 403);
        }

        $request->attributes->set('user_permissions', app(RoleMiddleware::class)->getPermissions($user->role));

        return $next($request);
    }
}
