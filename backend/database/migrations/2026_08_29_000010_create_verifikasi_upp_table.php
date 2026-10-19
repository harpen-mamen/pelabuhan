<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifikasi_upp', function (Blueprint $table) {
            $table->id('id_verifikasi');
            $table->foreignId('id_manifest')->constrained('manifest', 'id_manifest')->onDelete('cascade');
            $table->foreignId('id_petugas_upp')->constrained('users', 'id_user')->onDelete('cascade');
            $table->enum('status_verifikasi', ['disetujui', 'ditolak', 'revisi'])->default('disetujui');
            $table->text('catatan')->nullable();
            $table->dateTime('tgl_verifikasi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifikasi_upp');
    }
};
