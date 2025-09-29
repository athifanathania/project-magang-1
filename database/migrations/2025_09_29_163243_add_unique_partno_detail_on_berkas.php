<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            // (opsional) pastikan panjang index aman untuk MySQL lawas:
            // $table->string('kode_berkas', 191)->change();
            // $table->string('detail', 191)->change();

            $table->unique(['kode_berkas', 'detail'], 'berkas_partno_detail_unique');
        });
    }

    public function down(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            $table->dropUnique('berkas_partno_detail_unique');
        });
    }
};
