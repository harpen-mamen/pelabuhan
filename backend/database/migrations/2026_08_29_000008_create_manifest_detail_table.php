<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manifest_detail', function (Blueprint $table) {
            $table->id('id_manifest_detail');
            $table->foreignId('id_manifest')->constrained('manifest', 'id_manifest')->onDelete('cascade');
            $table->foreignId('id_barang')->constrained('barang', 'id_barang')->onDelete('cascade');
            $table->float('jumlah_manifest');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest_detail');
    }
};
