<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imm_lampirans', function (Blueprint $table) {
            // urutan antar-saudara (satu parent_id)
            $table->unsignedInteger('sort_order')->nullable()->after('parent_id');

            // index gabungan buat query cepat
            $table->index(['parent_id', 'sort_order'], 'imm_lampirans_parent_sort_idx');
        });

        // seed nilai awal supaya mengikuti id (aman & stabil)
        DB::statement('UPDATE imm_lampirans SET sort_order = id WHERE sort_order IS NULL');
    }

    public function down(): void
    {
        Schema::table('imm_lampirans', function (Blueprint $table) {
            $table->dropIndex('imm_lampirans_parent_sort_idx');
            $table->dropColumn('sort_order');
        });
    }
};
