<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tables = ['imm_manual_mutu','imm_prosedur','imm_instruksi_standar','imm_formulir'];

        foreach ($tables as $tbl) {
            if (! Schema::hasTable($tbl)) continue;

            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (! Schema::hasColumn($tbl, 'revision')) {
                    $table->string('revision', 20)->nullable()->after('file');
                }
                if (! Schema::hasColumn($tbl, 'effective_at')) {
                    $table->date('effective_at')->nullable()->after('revision');
                }
                if (! Schema::hasColumn($tbl, 'file_versions')) {
                    $table->json('file_versions')->nullable()->after('effective_at');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['imm_manual_mutu','imm_prosedur','imm_instruksi_standar','imm_formulir'];
        foreach ($tables as $tbl) {
            if (! Schema::hasTable($tbl)) continue;

            Schema::table($tbl, function (Blueprint $table) {
                $table->dropColumn(['revision','effective_at','file_versions']);
            });
        }
    }
};
