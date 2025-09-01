<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) pastikan ON DELETE CASCADE
        Schema::table('lampirans', function (Blueprint $table) {
            // drop FK lama (nama default seperti di screenshot kamu)
            $table->dropForeign('lampirans_berkas_id_foreign');
            $table->dropForeign('lampirans_parent_id_foreign');
        });

        Schema::table('lampirans', function (Blueprint $table) {
            $table->foreign('berkas_id')
                ->references('id')->on('berkas')
                ->cascadeOnDelete();

            $table->foreign('parent_id')
                ->references('id')->on('lampirans')
                ->cascadeOnDelete();

            // 2) index gabungan untuk performa (tambahan; aman meski sudah ada index per kolom)
            $table->index(['berkas_id', 'parent_id'], 'lampirans_berkas_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->dropIndex('lampirans_berkas_parent_idx');

            // balikin FK (tanpa cascade). Sesuaikan kebutuhanmu;
            // bisa juga tetap cascade kalau kamu ingin.
            $table->dropForeign(['berkas_id']);
            $table->dropForeign(['parent_id']);

            $table->foreign('berkas_id')->references('id')->on('berkas');
            $table->foreign('parent_id')->references('id')->on('lampirans');
        });
    }
};

