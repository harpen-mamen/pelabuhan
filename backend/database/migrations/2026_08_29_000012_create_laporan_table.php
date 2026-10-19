<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan', function (Blueprint $table) {
            $table->id('id_laporan');
            $table->string('jenis_laporan');
            $table->date('periode_awal');
            $table->date('periode_akhir');
            $table->foreignId('id_user_generate')->constrained('users', 'id_user')->onDelete('cascade');
            $table->string('file_path')->nullable();
            $table->json('parameter')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan');
    }
};
