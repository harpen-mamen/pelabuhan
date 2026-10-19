<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * ========================================================================
     * ROLE YANG BOLEH AKSES USER MANAGEMENT + TIER OTORITAS
     * ========================================================================
     * - super_admin: full access termasuk mengelola user bertingkat super_admin
     * - admin       : mengelola user OPERASIONAL, TIDAK boleh:
     *                   * membuat super_admin
     *                   * mengubah role user manapun menjadi super_admin
     *                   * menonaktifkan super_admin
     *                   * menonaktifkan diri sendiri
     * ========================================================================
     */

    /**
     * Daftar role yang DIBUTUHKAN untuk endpoint ini.
     */
    private array $availableRoles = [
        'super_admin', 'admin', 'operator', 'upp', 'checker',
        'pelanggan', 'manajer', 'kantor_perhubungan',
    ];

    public function index(Request $request)
    {
        $query = User::query();
        if (Auth::guard('api')->user()?->role !== 'super_admin') {
            $query->where('role', '!=', 'super_admin');
        }

        // Filter by role
        if ($request->role) {
            $query->where("role", $request->role);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where("is_active", $request->boolean('is_active'));
        }

        // Search
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where("nama", "LIKE", "%" . $request->search . "%")
                  ->orWhere("username", "LIKE", "%" . $request->search . "%")
                  ->orWhere("email", "LIKE", "%" . $request->search . "%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $data = $query->orderBy("created_at", "desc")->paginate($perPage);

        return response()->json([
            "status" => "success",
            "data" => $data,
            "meta" => [
                "total" => $data->total(),
                "per_page" => $data->perPage(),
                "current_page" => $data->currentPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = Auth::guard("api")->user();

        // Validasi role - super_admin HANYA boleh dibuat oleh super_admin lain
        if ($request->role === "super_admin" && $currentUser->role !== "super_admin") {
            return response()->json([
                "status" => "error",
                "message" => "Hanya super_admin yang boleh membuat akun super_admin baru.",
                "code" => "PRIVILEGE_ESCALATION_DENIED",
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            "nama" => "required|string|max:255",
            "username" => "required|string|unique:users,username|min:3|max:50",
            "email" => "nullable|email|unique:users,email|max:255",
            "password" => "required|string|min:6|max:100",
            "kontak" => "nullable|string|max:20",
            "role" => ["required", Rule::in($this->availableRoles)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => "Validasi gagal",
                "errors" => $validator->errors(),
                "code" => "VALIDATION_ERROR"
            ], 422);
        }

        // Cek apakah username sudah ada
        if (User::where("username", $request->username)->exists()) {
            return response()->json([
                "status" => "error",
                "message" => "Username sudah digunakan",
                "code" => "USERNAME_EXISTS"
            ], 422);
        }

        $user = User::create([
            "nama" => $request->nama,
            "username" => $request->username,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "kontak" => $request->kontak,
            "role" => $request->role,
            "is_active" => true,
            "created_by" => $currentUser->id_user,
        ]);

        // Auto-create pelanggan profile
        if ($user->role === "pelanggan") {
            $perusahaan = $request->id_perusahaan ? \App\Models\Perusahaan::find($request->id_perusahaan) : null;
            Pelanggan::create([
                "id_user" => $user->id_user,
                "nama" => $user->nama,
                "kontak" => $user->kontak,
                "id_perusahaan" => $perusahaan?->id_perusahaan,
            ]);
        }

        // Audit log
        AuditLogger::log(
            "USER_CREATE",
            "users",
            $user->id_user,
            null,
            $user->only(["nama", "username", "email", "role"])
        );

        return response()->json([
            "status" => "success",
            "message" => "User berhasil ditambahkan",
            "data" => $user->load("pelanggan"),
        ], 201);
    }

    public function show($id)
    {
        $user = User::with("pelanggan.perusahaan")->find($id);

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "User tidak ditemukan",
                "code" => "USER_NOT_FOUND"
            ], 404);
        }

        if (Auth::guard('api')->user()?->role !== 'super_admin' && $user->role === 'super_admin') {
            return response()->json([
                "status" => "error",
                "message" => "Akun ini tidak dapat diakses oleh admin operasional.",
                "code" => "USER_ACCESS_DENIED"
            ], 403);
        }

        return response()->json([
            "status" => "success",
            "data" => $user
        ]);
    }

    public function update(Request $request, $id)
    {
        $currentUser = Auth::guard("api")->user();
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "User tidak ditemukan",
                "code" => "USER_NOT_FOUND"
            ], 404);
        }

        // === PROTEKSI TIER OTORITAS ===
        // Admin biasa tidak boleh mengubah role super_admin atau mengubah role siapapun menjadi super_admin
        if ($currentUser->role !== "super_admin") {
            if ($user->role === "super_admin") {
                return response()->json([
                    "status" => "error",
                    "message" => "Anda tidak memiliki izin untuk mengubah akun super_admin.",
                    "code" => "PROTECTED_ACCOUNT",
                ], 403);
            }
            if ($request->has("role") && $request->role === "super_admin") {
                return response()->json([
                    "status" => "error",
                    "message" => "Hanya super_admin yang dapat menetapkan role super_admin.",
                    "code" => "PRIVILEGE_ESCALATION_DENIED",
                ], 403);
            }
        }

        // Super_admin tidak boleh didemote oleh admin biasa
        if ($currentUser->role === "admin" && $user->role === "super_admin") {
            return response()->json([
                "status" => "error",
                "message" => "Admin biasa tidak boleh mengubah akun super_admin.",
                "code" => "PROTECTED_ACCOUNT",
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            "nama" => "sometimes|string|max:255",
            "username" => ["sometimes", "string", Rule::unique("users", "username")->ignore($id, "id_user"), "min:3", "max:50"],
            "email" => ["nullable", "email", Rule::unique("users", "email")->ignore($id, "id_user"), "max:255"],
            "password" => "nullable|string|min:6|max:100",
            "kontak" => "nullable|string|max:20",
            "role" => [Rule::in($this->availableRoles)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "error",
                "message" => "Validasi gagal",
                "errors" => $validator->errors(),
                "code" => "VALIDATION_ERROR"
            ], 422);
        }

        // Snapshot data lama untuk audit
        $oldData = $user->only(["nama", "username", "email", "kontak", "role", "is_active"]);
        $updateData = $request->only(["nama", "username", "email", "kontak", "role"]);

        if ($request->password) {
            $updateData["password"] = Hash::make($request->password);
        }

        $user->update($updateData);

        // Update profil pelanggan jika role-nya pelanggan
        if ($user->role === "pelanggan") {
            $pelangganData = [
                "nama" => $user->nama,
                "kontak" => $user->kontak,
            ];
            if ($request->id_perusahaan) {
                $pelangganData["id_perusahaan"] = $request->id_perusahaan;
            }
            Pelanggan::updateOrCreate(
                ["id_user" => $user->id_user],
                $pelangganData
            );
        }

        // Audit log
        AuditLogger::log(
            "USER_UPDATE",
            "users",
            $user->id_user,
            $oldData,
            $user->only(["nama", "username", "email", "kontak", "role", "is_active"])
        );

        return response()->json([
            "status" => "success",
            "message" => "User berhasil diupdate",
            "data" => $user->fresh()->load("pelanggan")
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "User tidak ditemukan",
                "code" => "USER_NOT_FOUND"
            ], 404);
        }

        // Soft delete - set is_active = false
        $user->update(["is_active" => false]);

        return response()->json([
            "status" => "success",
            "message" => "User berhasil dinonaktifkan"
        ]);
    }

    /**
     * Toggle active status user
     */
    public function toggleActive($id)
    {
        $currentUser = Auth::guard("api")->user();
        // Tangani baik model User maupun Collection dari guard test mode
        $currentUserRole = null;
        $currentUserId = null;
        if ($currentUser instanceof \App\Models\User) {
            $currentUserRole = $currentUser->role;
            $currentUserId = $currentUser->id_user;
        } elseif ($currentUser instanceof \Illuminate\Support\Collection) {
            $first = $currentUser->first();
            if ($first instanceof \App\Models\User) {
                $currentUserRole = $first->role;
                $currentUserId = $first->id_user;
            }
        }
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "User tidak ditemukan",
                "code" => "USER_NOT_FOUND"
            ], 404);
        }

        // Admin tidak bisa menonaktifkan dirinya sendiri
        if ($user->id_user === $currentUserId) {
            return response()->json([
                "status" => "error",
                "message" => "Anda tidak dapat menonaktifkan akun sendiri",
                "code" => "CANNOT_DISABLE_SELF"
            ], 422);
        }

        // Super_admin hanya bisa dinonaktifkan oleh super_admin lain
        if ($user->role === "super_admin" && $currentUserRole !== "super_admin") {
            return response()->json([
                "status" => "error",
                "message" => "Akun super_admin hanya bisa dinonaktifkan oleh super_admin lain.",
                "code" => "PROTECTED_ACCOUNT",
            ], 403);
        }

        $newStatus = !$user->is_active;
        $user->update(["is_active" => $newStatus]);

        // Audit log
        AuditLogger::log(
            $newStatus ? "USER_ACTIVATE" : "USER_DEACTIVATE",
            "users",
            $user->id_user,
            ["is_active" => !$newStatus],
            ["is_active" => $newStatus]
        );

        return response()->json([
            "status" => "success",
            "message" => "User berhasil " . ($newStatus ? "diaktifkan" : "dinonaktifkan"),
            "data" => [
                "id_user" => $user->id_user,
                "is_active" => $user->is_active,
            ]
        ]);
    }

    /**
     * Get available roles (for dropdown)
     */
    public function roles()
    {
        $currentUser = Auth::guard("api")->user();
        $isSuperAdmin = $currentUser?->role === "super_admin";

        $roleDescriptions = [
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Akses penuh TERMASUK mengelola user bertingkat super_admin',
                'color' => 'dark',
                'restricted' => true,
                'requires_super_admin_to_assign' => true,
            ],
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Operasional harian, TIDAK bisa mengelola super_admin',
                'color' => 'danger',
            ],
            'operator' => [
                'name' => 'Operator',
                'description' => 'Membuat & mengelola booking, manifest, voyage',
                'color' => 'primary',
            ],
            'upp' => [
                'name' => 'Syahbandar',
                'description' => 'Verifikasi & validasi manifest, audit',
                'color' => 'warning',
            ],
            'checker' => [
                'name' => 'Checker',
                'description' => 'Input realisasi bongkar/muat',
                'color' => 'info',
            ],
            'pelanggan' => [
                'name' => 'Pelanggan',
                'description' => 'Membuat booking & tracking pengiriman',
                'color' => 'success',
            ],
            'manajer' => [
                'name' => 'Manajer',
                'description' => 'Laporan & analytics, audit',
                'color' => 'secondary',
            ],
            'kantor_perhubungan' => [
                'name' => 'Kantor Perhubungan',
                'description' => 'Oversight lintas perusahaan, PNBP, audit',
                'color' => 'purple',
            ],
        ];

        // Sembunyikan super_admin dari dropdown jika bukan super_admin
        if (!$isSuperAdmin && isset($roleDescriptions['super_admin'])) {
            unset($roleDescriptions['super_admin']);
        }

        return response()->json([
            "status" => "success",
            "data" => $roleDescriptions,
            "meta" => [
                "current_user_role" => $currentUser?->role,
                "is_super_admin" => $isSuperAdmin,
            ],
        ]);
    }

    /**
     * Get user statistics
     */
    public function statistics()
    {
        $data = [
            "total" => User::count(),
            "aktif" => User::where("is_active", true)->count(),
            "nonaktif" => User::where("is_active", false)->count(),
            "by_role" => User::selectRaw("role, COUNT(*) as total")
                ->groupBy("role")
                ->get(),
            "recent_registrations" => User::orderBy("created_at", "desc")
                ->limit(5)
                ->get(["id_user", "nama", "username", "role", "created_at"]),
        ];

        return response()->json([
            "status" => "success",
            "data" => $data
        ]);
    }
}
