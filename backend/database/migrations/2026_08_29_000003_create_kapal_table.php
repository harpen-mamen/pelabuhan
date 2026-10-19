<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kapal', function (Blueprint $table) {
            $table->id('id_kapal');
            $table->string('nama_kapal');
            $table->float('kapasitas_dwt')->default(0);
            $table->string('jenis_kapal')->nullable();
            $table->string('nomor_imo')->nullable();
            $table->string('pemilik')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kapal');
    }
};
