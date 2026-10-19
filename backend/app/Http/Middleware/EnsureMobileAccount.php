<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Silakan login kembali.',
                'code' => 'AUTH_001',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun tidak aktif. Hubungi admin utama.',
                'code' => 'AUTH_002',
            ], 403);
        }

        if ($user->role === 'super_admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun admin utama hanya dapat digunakan melalui panel web.',
                'code' => 'WEB_ONLY_ROLE',
            ], 403);
        }

        return $next($request);
    }
}
