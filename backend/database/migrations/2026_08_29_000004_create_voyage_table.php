<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voyage', function (Blueprint $table) {
            $table->id('id_voyage');
            $table->foreignId('id_kapal')->constrained('kapal', 'id_kapal')->onDelete('cascade');
            $table->string('nomor_voyage')->unique();
            $table->string('rute');
            $table->date('tgl_berangkat');
            $table->date('tgl_tiba')->nullable();
            $table->enum('status', ['dijadwalkan', 'berlayar', 'sandar', 'selesai', 'dibatalkan'])->default('dijadwalkan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voyage');
    }
};
