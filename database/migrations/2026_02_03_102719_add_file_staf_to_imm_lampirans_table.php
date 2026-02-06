<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('imm_lampirans', function (Blueprint $table) {
            // Menambahkan kolom file_staf setelah kolom file
            $table->string('file_staf')->nullable()->after('file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imm_lampirans', function (Blueprint $table) {
            $table->dropColumn('file_staf');
        });
    }
};
