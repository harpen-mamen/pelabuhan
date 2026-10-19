<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id('id_log');
            $table->foreignId('id_user')->nullable()->constrained('users', 'id_user')->onDelete('set null');
            $table->string('aksi');
            $table->string('tabel_terkait')->nullable();
            $table->unsignedBigInteger('id_data_terkait')->nullable();
            $table->json('data_lama')->nullable();
            $table->json('data_baru')->nullable();
            $table->string('ip_address')->nullable();
            $table->dateTime('waktu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
