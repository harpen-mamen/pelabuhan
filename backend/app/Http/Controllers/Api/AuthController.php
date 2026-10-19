<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        $credentials = $request->only('username', 'password');

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Username atau password salah',
            ], 401);
        }

        $user = Auth::guard('api')->user();

        // Cek apakah akun nonaktif
        if (!$user->is_active) {
            Auth::guard('api')->logout();
            return response()->json([
                'status' => 'error',
                'message' => 'Akun Anda tidak aktif. Hubungi admin.',
            ], 403);
        }

        if ($user->role === 'super_admin') {
            Auth::guard('api')->logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Admin utama menggunakan panel web. Aplikasi mobile ditujukan untuk staf operasional dan pelanggan.',
                'code' => 'WEB_ONLY_ROLE',
            ], 403);
        }

        // Auto-create pelanggan profile (non-blocking)
        if ($user->role === 'pelanggan') {
            try {
                Pelanggan::firstOrCreate(
                    ['id_user' => $user->id_user],
                    [
                        'nama' => $user->nama,
                        'kontak' => $user->kontak,
                    ]
                );
            } catch (\Exception $e) {
                // Ignore if gagal
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id_user' => $user->id_user,
                    'nama' => $user->nama,
                    'username' => $user->username,
                    'role' => $user->role,
                    'kontak' => $user->kontak,
                ],
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            ],
        ]);
    }

    public function me()
    {
        $user = Auth::guard('api')->user();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id_user' => $user->id_user,
                'nama' => $user->nama,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'kontak' => $user->kontak,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('api')->logout();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil',
        ]);
    }

    public function refresh()
    {
        $token = Auth::guard('api')->refresh();

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'kontak' => 'nullable|string',
            // Registrasi mandiri hanya untuk pelanggan. Role staf dibuat admin utama.
            'role' => 'prohibited',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'data' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'nama' => $request->nama,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'kontak' => $request->kontak,
            'role' => 'pelanggan',
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Registrasi berhasil',
            'data' => $user,
        ], 201);
    }
}
