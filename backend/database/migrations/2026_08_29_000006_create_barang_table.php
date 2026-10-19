<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->id('id_barang');
            $table->foreignId('id_booking')->constrained('booking', 'id_booking')->onDelete('cascade');
            $table->string('nama_barang');
            $table->enum('kategori', ['umum', 'berbahaya', 'bahan_pokok', 'lainnya'])->default('umum');
            $table->float('berat')->default(0);
            $table->float('volume')->default(0);
            $table->integer('jumlah_kemasan')->default(1);
            $table->string('satuan_berat')->default('kg');
            $table->string('satuan_volume')->default('m3');
            $table->string('dokumen_izin')->nullable();
            $table->enum('status_muatan', ['belum_dimuat', 'dimuat', 'dibongkar', 'selesai'])->default('belum_dimuat');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
