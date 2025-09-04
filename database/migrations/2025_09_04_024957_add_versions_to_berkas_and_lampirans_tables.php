<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            // simpan riwayat versi dokumen berkas
            $table->json('dokumen_versions')->nullable()->after('dokumen');
            // kalau DB kamu tidak support JSON, ganti ke:
            // $table->longText('dokumen_versions')->nullable()->after('dokumen');
        });

        Schema::table('lampirans', function (Blueprint $table) {
            // simpan riwayat versi file lampiran
            $table->json('file_versions')->nullable()->after('file');
            // alternatif untuk DB lama:
            // $table->longText('file_versions')->nullable()->after('file');
        });
    }

    public function down(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            $table->dropColumn('dokumen_versions');
        });

        Schema::table('lampirans', function (Blueprint $table) {
            $table->dropColumn('file_versions');
        });
    }
};
