<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $tables = [
        'imm_formulir',
        'imm_instruksi_standar',
        'imm_manual_mutu',
        'imm_prosedur',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'versions')) {
                    $t->dropColumn('versions');
                }
                if (Schema::hasColumn($table, 'current_revision')) {
                    $t->dropColumn('current_revision');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'versions')) {
                    $t->longText('versions')->nullable()->after('effective_at');
                }
                if (!Schema::hasColumn($table, 'current_revision')) {
                    $t->string('current_revision', 20)->nullable()->after('versions');
                }
            });
        }
    }
};
