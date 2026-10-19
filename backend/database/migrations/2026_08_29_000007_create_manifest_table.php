<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manifest', function (Blueprint $table) {
            $table->id('id_manifest');
            $table->string('nomor_manifest')->unique();
            $table->foreignId('id_voyage')->constrained('voyage', 'id_voyage')->onDelete('cascade');
            $table->foreignId('id_booking')->constrained('booking', 'id_booking')->onDelete('cascade');
            $table->foreignId('id_user_pembuat')->constrained('users', 'id_user')->onDelete('cascade');
            $table->date('tgl_manifest');
            $table->float('total_berat')->default(0);
            $table->float('total_volume')->default(0);
            $table->enum('status', ['draft', 'menunggu_verifikasi', 'disetujui', 'ditolak', 'perlu_klarifikasi', 'selesai'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest');
    }
};
