<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add super_admin ke enum role
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','operator','checker','manajer','pelanggan','upp','kantor_perhubungan') DEFAULT 'pelanggan'");

        // Tambah kolom 2FA & last login utk audit admin
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_required')->default(false)->after('is_active')
                ->comment('Wajib pakai verifikasi tambahan untuk aksi sensitif');
            $table->string('two_factor_secret', 100)->nullable()->after('two_factor_required');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
            $table->integer('failed_login_attempts')->default(0)->after('two_factor_confirmed_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('last_login_at')->nullable()->after('locked_until');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->unsignedBigInteger('created_by')->nullable()->after('last_login_ip')
                ->comment('User yang membuat akun ini (audit chain)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_required', 'two_factor_secret', 'two_factor_enabled',
                'two_factor_confirmed_at', 'failed_login_attempts', 'locked_until',
                'last_login_at', 'last_login_ip', 'created_by',
            ]);
        });
    }
};
