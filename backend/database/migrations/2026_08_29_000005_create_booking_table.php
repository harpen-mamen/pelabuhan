<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking', function (Blueprint $table) {
            $table->id('id_booking');
            $table->string('kode_booking')->unique();
            $table->foreignId('id_pelanggan')->constrained('pelanggan', 'id_pelanggan')->onDelete('cascade');
            $table->date('tgl_booking');
            $table->string('asal');
            $table->string('tujuan');
            $table->text('catatan')->nullable();
            $table->enum('status', ['menunggu', 'diproses', 'manifest', 'siap_dimuat', 'dimuat', 'dalam_perjalanan', 'diterima', 'ditolak', 'dibatalkan'])->default('menunggu');
            $table->decimal('estimasi_biaya', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking');
    }
};
