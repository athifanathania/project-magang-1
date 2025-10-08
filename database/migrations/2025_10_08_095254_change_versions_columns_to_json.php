<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Normalisasi data invalid -> '[]' biar JSON_VALID = 1
        DB::statement("UPDATE berkas SET dokumen_versions = '[]' WHERE dokumen_versions IS NULL OR JSON_VALID(dokumen_versions) = 0");
        DB::statement("UPDATE lampirans SET file_versions = '[]' WHERE file_versions IS NULL OR JSON_VALID(file_versions) = 0");
        DB::statement("UPDATE imm_formulir SET file_versions = '[]' WHERE file_versions IS NULL OR JSON_VALID(file_versions) = 0");
        DB::statement("UPDATE imm_instruksi_standar SET file_versions = '[]' WHERE file_versions IS NULL OR JSON_VALID(file_versions) = 0");
        DB::statement("UPDATE imm_manual_mutu SET file_versions = '[]' WHERE file_versions IS NULL OR JSON_VALID(file_versions) = 0");
        DB::statement("UPDATE imm_prosedur SET file_versions = '[]' WHERE file_versions IS NULL OR JSON_VALID(file_versions) = 0");

        // 2) Ubah tipe ke JSON
        Schema::table('berkas', function (Blueprint $t) {
            $t->json('dokumen_versions')->nullable()->change();
        });

        Schema::table('lampirans', function (Blueprint $t) {
            $t->json('file_versions')->nullable()->change();
        });

        Schema::table('imm_formulir', function (Blueprint $t) {
            $t->json('file_versions')->nullable()->change();
        });
        Schema::table('imm_instruksi_standar', function (Blueprint $t) {
            $t->json('file_versions')->nullable()->change();
        });
        Schema::table('imm_manual_mutu', function (Blueprint $t) {
            $t->json('file_versions')->nullable()->change();
        });
        Schema::table('imm_prosedur', function (Blueprint $t) {
            $t->json('file_versions')->nullable()->change();
        });

        // (OPSIONAL) jadikan keywords -> JSON juga
        // DB::statement("UPDATE berkas SET keywords = '[]' WHERE keywords IS NULL OR JSON_VALID(keywords) = 0");
        // Schema::table('berkas', fn (Blueprint $t) => $t->json('keywords')->nullable()->change());
        // DB::statement("UPDATE lampirans SET keywords = '[]' WHERE keywords IS NULL OR JSON_VALID(keywords) = 0");
        // Schema::table('lampirans', fn (Blueprint $t) => $t->json('keywords')->nullable()->change());
        // DB::statement("UPDATE imm_formulir SET keywords = '[]' WHERE keywords IS NULL OR JSON_VALID(keywords) = 0");
        // Schema::table('imm_formulir', fn (Blueprint $t) => $t->json('keywords')->nullable()->change());
        // ...ulang untuk semua tabel IMM lain bila mau
    }

    public function down(): void
    {
        // Balik lagi ke LONGTEXT (data JSON akan tersimpan sebagai string)
        Schema::table('berkas', function (Blueprint $t) {
            $t->longText('dokumen_versions')->nullable()->change();
        });

        Schema::table('lampirans', function (Blueprint $t) {
            $t->longText('file_versions')->nullable()->change();
        });

        Schema::table('imm_formulir', function (Blueprint $t) {
            $t->longText('file_versions')->nullable()->change();
        });
        Schema::table('imm_instruksi_standar', function (Blueprint $t) {
            $t->longText('file_versions')->nullable()->change();
        });
        Schema::table('imm_manual_mutu', function (Blueprint $t) {
            $t->longText('file_versions')->nullable()->change();
        });
        Schema::table('imm_prosedur', function (Blueprint $t) {
            $t->longText('file_versions')->nullable()->change();
        });

        // (OPSIONAL) keywords balik ke longtext bila tadi diubah
        // Schema::table('berkas', fn (Blueprint $t) => $t->longText('keywords')->nullable()->change());
        // Schema::table('lampirans', fn (Blueprint $t) => $t->longText('keywords')->nullable()->change());
        // Schema::table('imm_formulir', fn (Blueprint $t) => $t->longText('keywords')->nullable()->change());
        // ...
    }
};
