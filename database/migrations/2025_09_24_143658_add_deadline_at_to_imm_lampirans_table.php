<?php

// database/migrations/2025_XX_XX_000001_add_deadline_at_to_imm_lampirans_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('imm_lampirans', function (Blueprint $t) {
            $t->date('deadline_at')->nullable()->after('keywords');
            $t->index('deadline_at');
        });
    }
    public function down(): void {
        Schema::table('imm_lampirans', function (Blueprint $t) {
            $t->dropIndex(['deadline_at']);
            $t->dropColumn('deadline_at');
        });
    }
};
