<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\KapalController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\ManifestController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\PelangganController;
use App\Http\Controllers\Api\PerusahaanController;
use App\Http\Controllers\Api\RealisasiController;
use App\Http\Controllers\Api\TarifController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VoyageController;
use Illuminate\Support\Facades\Route;

// Login dan registrasi pelanggan dilindungi pembatasan percobaan.
Route::middleware('throttle:5,1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);
});

Route::get('tracking/{kode}', [BookingController::class, 'tracking'])
    ->middleware('throttle:30,1');

Route::middleware(['auth:api', 'mobile.account'])->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('auth/permissions', function () {
        return response()->json([
            'status' => 'success',
            'data' => \App\Http\Middleware\RoleMiddleware::getRolePermissionsMap(),
        ]);
    });

    // Akun dikelola admin utama; admin operasional hanya dapat mengelola staf non-super-admin.
    Route::get('users/roles', [UserController::class, 'roles'])->middleware('permission:users.read');
    Route::get('users/statistics', [UserController::class, 'statistics'])->middleware('permission:users.read');
    Route::get('users', [UserController::class, 'index'])->middleware('permission:users.read');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::get('users/{id}', [UserController::class, 'show'])->middleware('permission:users.read');
    Route::match(['put', 'patch'], 'users/{id}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('users/{id}', [UserController::class, 'destroy'])->middleware('permission:users.deactivate');
    Route::put('users/{id}/toggle-active', [UserController::class, 'toggleActive'])->middleware('permission:users.deactivate');

    // Master data: admin membuat perubahan; operator boleh membaca data yang diperlukan.
    Route::apiResource('pelanggan', PelangganController::class)->only(['index', 'show'])->middleware('permission:pelanggan.read');
    Route::apiResource('pelanggan', PelangganController::class)->only(['store'])->middleware('permission:pelanggan.create');
    Route::apiResource('pelanggan', PelangganController::class)->only(['update'])->middleware('permission:pelanggan.update');
    Route::apiResource('pelanggan', PelangganController::class)->only(['destroy'])->middleware('permission:pelanggan.delete');

    Route::apiResource('perusahaan', PerusahaanController::class)->only(['index', 'show'])->middleware('permission:perusahaan.read');
    Route::apiResource('perusahaan', PerusahaanController::class)->only(['store'])->middleware('permission:perusahaan.create');
    Route::apiResource('perusahaan', PerusahaanController::class)->only(['update'])->middleware('permission:perusahaan.update');
    Route::apiResource('perusahaan', PerusahaanController::class)->only(['destroy'])->middleware('permission:perusahaan.delete');

    Route::apiResource('kapal', KapalController::class)->only(['index', 'show'])->middleware('permission:kapal.read');
    Route::apiResource('kapal', KapalController::class)->only(['store'])->middleware('permission:kapal.create');
    Route::apiResource('kapal', KapalController::class)->only(['update'])->middleware('permission:kapal.update');
    Route::apiResource('kapal', KapalController::class)->only(['destroy'])->middleware('permission:kapal.delete');

    Route::apiResource('voyage', VoyageController::class)->only(['index', 'show'])->middleware('permission:voyage.read');
    Route::apiResource('voyage', VoyageController::class)->only(['store'])->middleware('permission:voyage.create');
    Route::apiResource('voyage', VoyageController::class)->only(['update'])->middleware('permission:voyage.update');
    Route::apiResource('voyage', VoyageController::class)->only(['destroy'])->middleware('permission:voyage.delete');
    Route::get('voyage/{id}/cek-kapasitas', [VoyageController::class, 'cekKapasitas'])->middleware('permission:voyage.read');
    Route::post('voyage/{id}/berangkat', [VoyageController::class, 'berangkat'])->middleware('permission:voyage.status');
    Route::post('voyage/{id}/tiba', [VoyageController::class, 'tiba'])->middleware('permission:voyage.status');

    Route::get('tarif', [TarifController::class, 'index'])->middleware('permission:tarif.read');
    Route::get('tarif/hitung', [TarifController::class, 'hitung'])->middleware('permission:tarif.read');
    Route::post('tarif', [TarifController::class, 'store'])->middleware('permission:tarif.manage');
    Route::put('tarif/{id}', [TarifController::class, 'update'])->middleware('permission:tarif.manage');
    Route::delete('tarif/{id}', [TarifController::class, 'destroy'])->middleware('permission:tarif.manage');

    // Booking pelanggan selalu dibatasi ke profilnya sendiri oleh controller.
    Route::get('booking', [BookingController::class, 'index'])->middleware('permission:booking.read');
    Route::get('booking/{id}', [BookingController::class, 'show'])->middleware('permission:booking.read');
    Route::get('booking/{id}/transitions', [BookingController::class, 'getAvailableTransitions'])->middleware('permission:booking.status');
    Route::post('booking', [BookingController::class, 'store'])->middleware('permission:booking.create');
    Route::put('booking/{id}', [BookingController::class, 'update'])->middleware('permission:booking.update');
    Route::put('booking/{id}/status', [BookingController::class, 'updateStatus'])->middleware('permission:booking.status');
    Route::delete('booking/{id}', [BookingController::class, 'destroy'])->middleware('permission:booking.delete');

    // Operator menyiapkan manifest; UPP memberi keputusan verifikasi.
    Route::get('manifest', [ManifestController::class, 'index'])->middleware('permission:manifest.read');
    Route::get('manifest/statistics', [ManifestController::class, 'statistics'])->middleware('permission:manifest.verifikasi');
    Route::get('manifest/{id}', [ManifestController::class, 'show'])->middleware('permission:manifest.read');
    Route::post('manifest', [ManifestController::class, 'store'])->middleware('permission:manifest.create');
    Route::put('manifest/{id}/submit', [ManifestController::class, 'submit'])->middleware('permission:manifest.submit');
    Route::put('manifest/{id}/verifikasi', [ManifestController::class, 'verifikasi'])->middleware('permission:manifest.verifikasi');

    // Checker mencatat muat/bongkar; role lain hanya membaca sesuai tugas.
    Route::get('realisasi', [RealisasiController::class, 'index'])->middleware('permission:realisasi.read');
    Route::get('realisasi/statistics', [RealisasiController::class, 'statistics'])->middleware('permission:realisasi.statistics');
    Route::get('realisasi/selisih', [RealisasiController::class, 'selisih'])->middleware('permission:realisasi.selisih');
    Route::post('realisasi', [RealisasiController::class, 'store'])->middleware('permission:realisasi.create');

    Route::get('dashboard/summary', [DashboardController::class, 'summary'])->middleware('permission:dashboard.view');
    Route::get('dashboard/arus-barang', [DashboardController::class, 'arusBarang'])->middleware('permission:laporan.view');
    Route::get('dashboard/trend-bulanan', [DashboardController::class, 'trendBulanan'])->middleware('permission:dashboard.trend');
    Route::get('dashboard/activity-heatmap', [DashboardController::class, 'activityHeatmap'])->middleware('permission:dashboard.full');

    Route::get('laporan/arus-barang', [LaporanController::class, 'arusBarang'])->middleware('permission:laporan.view');
    Route::get('laporan/selisih-manifest', [LaporanController::class, 'selisih'])->middleware('permission:realisasi.selisih');
    Route::get('laporan/manifest', [LaporanController::class, 'manifest'])->middleware('permission:laporan.view');
    Route::get('laporan/kepatuhan', [LaporanController::class, 'kepatuhan'])->middleware('permission:laporan.kepatuhan');
    Route::get('laporan/pnbp', [LaporanController::class, 'pnbp'])->middleware('permission:laporan.pnbp');
    Route::get('audit-log', [AuditLogController::class, 'index'])->middleware('permission:audit.read');

    Route::get('notifikasi', [NotifikasiController::class, 'index']);
    Route::put('notifikasi/{id}/read', [NotifikasiController::class, 'markAsRead']);
    Route::put('notifikasi/read-all', [NotifikasiController::class, 'markAllRead']);
});
