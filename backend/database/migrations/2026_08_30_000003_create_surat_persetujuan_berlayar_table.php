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
        Schema::create('surat_persetujuan_berlayar', function (Blueprint $table) {
            $table->id('id_spb');
            $table->foreignId('id_manifest')->constrained('manifest', 'id_manifest')->onDelete('cascade');
            $table->foreignId('id_verifikasi')->nullable()->constrained('verifikasi_upp', 'id_verifikasi')->onDelete('set null');
            $table->foreignId('id_sabandar')->constrained('users', 'id_user');
            $table->string('nomor_spb', 50)->unique();
            $table->dateTime('tgl_terbit');
            $table->text('tanda_tangan_elektronik')->nullable();
            $table->string('file_pdf_path', 255)->nullable();
            $table->string('hash_dokumen', 64)->nullable();
            $table->enum('status', ['draft', 'terbit', 'batal'])->default('draft');
            $table->timestamps();

            $table->index('id_manifest');
            $table->index('nomor_spb');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_persetujuan_berlayar');
    }
};
