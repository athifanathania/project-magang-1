<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_keywords_to_lampirans_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->json('keywords')->nullable()->after('file');
        });
    }

    public function down(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->dropColumn('keywords');
        });
    }
};
