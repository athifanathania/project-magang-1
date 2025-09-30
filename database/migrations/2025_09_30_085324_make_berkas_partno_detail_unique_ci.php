<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            // Hapus index unik lama kalau ada
            try { $table->dropUnique('berkas_partno_detail_unique'); } catch (\Throwable $e) {}

            // Kolom turunan LOWER(TRIM(..)) untuk keperluan index (case-insensitive)
            // 191 aman untuk MySQL lawas.
            $table->string('kode_berkas_ci', 191)->storedAs("LOWER(TRIM(`kode_berkas`))");
            $table->string('detail_ci', 191)->storedAs("LOWER(TRIM(`detail`))");

            // Index unik gabungan pada versi *_ci
            $table->unique(['kode_berkas_ci', 'detail_ci'], 'berkas_partno_detail_unique_ci');
        });
    }

    public function down(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            $table->dropUnique('berkas_partno_detail_unique_ci');
            $table->dropColumn(['kode_berkas_ci', 'detail_ci']);

            // (opsional) pulihkan index lama
            $table->unique(['kode_berkas', 'detail'], 'berkas_partno_detail_unique');
        });
    }
};
