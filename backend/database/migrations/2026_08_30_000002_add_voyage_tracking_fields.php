<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("voyage", function (Blueprint $table) {
            $table->dateTime("waktu_keberangkatan")->nullable()->after("tgl_berangkat")->comment("Waktu aktual kapal berangkat, di-set admin saat verifikasi");
            $table->dateTime("waktu_tiba_aktual")->nullable()->after("tgl_tiba")->comment("Waktu aktual kapal tiba di tujuan");
            $table->unsignedSmallInteger("estimasi_jam")->nullable()->after("rute")->comment("Estimasi waktu tempuh dalam jam");
            $table->decimal("lat_asal", 10, 7)->nullable()->after("estimasi_jam");
            $table->decimal("lng_asal", 10, 7)->nullable();
            $table->decimal("lat_tujuan", 10, 7)->nullable();
            $table->decimal("lng_tujuan", 10, 7)->nullable();
            $table->decimal("lat_transit", 10, 7)->nullable();
            $table->decimal("lng_transit", 10, 7)->nullable();
            $table->string("pelabuhan_asal")->nullable()->comment("Nama pelabuhan asal (mis. Surabaya)");
            $table->string("pelabuhan_transit")->nullable()->comment("Nama pelabuhan transit (mis. Makassar)");
            $table->string("pelabuhan_tujuan")->nullable()->comment("Nama pelabuhan tujuan (mis. Tahuna)");
        });
    }

    public function down(): void
    {
        Schema::table("voyage", function (Blueprint $table) {
            $table->dropColumn([
                "waktu_keberangkatan", "waktu_tiba_aktual", "estimasi_jam",
                "lat_asal", "lng_asal", "lat_tujuan", "lng_tujuan",
                "lat_transit", "lng_transit",
                "pelabuhan_asal", "pelabuhan_transit", "pelabuhan_tujuan",
            ]);
        });
    }
};

