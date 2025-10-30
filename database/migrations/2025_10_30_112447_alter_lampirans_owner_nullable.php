<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            // lepas FK dulu
            $table->dropForeign(['berkas_id']);
            $table->dropForeign(['regular_id']);

            // jadikan nullable
            $table->unsignedBigInteger('berkas_id')->nullable()->change();
            $table->unsignedBigInteger('regular_id')->nullable()->change();

            // pasang lagi FK
            $table->foreign('berkas_id')->references('id')->on('berkas')->cascadeOnDelete();
            $table->foreign('regular_id')->references('id')->on('regulars')->cascadeOnDelete();
        });

        // buang CHECK lama (kalau ada)
        try {
            DB::statement('ALTER TABLE lampirans DROP CHECK ck_lampirans_owner');
        } catch (\Throwable $e) {
            // abaikan kalau belum ada
        }

        // buat CHECK xor (MySQL 8+)
        DB::statement("
            ALTER TABLE lampirans
            ADD CONSTRAINT ck_lampirans_owner
            CHECK ((berkas_id IS NULL) <> (regular_id IS NULL))
        ");
    }

    public function down(): void
    {
        // drop CHECK
        try {
            DB::statement('ALTER TABLE lampirans DROP CHECK ck_lampirans_owner');
        } catch (\Throwable $e) {
            //
        }

        Schema::table('lampirans', function (Blueprint $table) {
            $table->dropForeign(['berkas_id']);
            $table->dropForeign(['regular_id']);

            // balik jadi NOT NULL (kalau memang mau)
            $table->unsignedBigInteger('berkas_id')->nullable(false)->change();
            $table->unsignedBigInteger('regular_id')->nullable(false)->change();

            $table->foreign('berkas_id')->references('id')->on('berkas')->cascadeOnDelete();
            $table->foreign('regular_id')->references('id')->on('regulars')->cascadeOnDelete();
        });
    }
};
