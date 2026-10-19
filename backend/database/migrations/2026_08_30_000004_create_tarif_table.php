<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tarif', function (Blueprint $table) {
            $table->id('id_tarif');
            $table->string('jenis_layanan', 100);
            $table->enum('kategori_barang', ['umum', 'berbahaya', 'bahan_pokok', 'b3', 'lainnya'])->default('umum');
            $table->string('rute', 255);
            $table->decimal('tarif_per_kg', 12, 2)->default(500);
            $table->decimal('tarif_per_m3', 12, 2)->default(1000);
            $table->date('berlaku_dari');
            $table->date('berlaku_hingga')->nullable();
            $table->timestamps();

            $table->index('kategori_barang');
            $table->index('rute');
            $table->index(['berlaku_dari', 'berlaku_hingga']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarif');
    }
};
