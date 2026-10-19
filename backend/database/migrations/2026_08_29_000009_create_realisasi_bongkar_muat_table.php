<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realisasi_bongkar_muat', function (Blueprint $table) {
            $table->id('id_realisasi');
            $table->foreignId('id_manifest_detail')->constrained('manifest_detail', 'id_manifest_detail')->onDelete('cascade');
            $table->foreignId('id_checker')->constrained('users', 'id_user')->onDelete('cascade');
            $table->float('jumlah_realisasi');
            $table->enum('jenis', ['muat', 'bongkar'])->default('muat');
            $table->text('keterangan_selisih')->nullable();
            $table->dateTime('waktu_input');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realisasi_bongkar_muat');
    }
};
