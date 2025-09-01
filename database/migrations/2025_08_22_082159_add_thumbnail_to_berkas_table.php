<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_thumbnail_to_berkas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            $table->string('thumbnail')->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            $table->dropColumn('thumbnail');
        });
    }
};

