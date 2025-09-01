<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            if (!Schema::hasColumn('berkas', 'keywords')) {
                $table->json('keywords')->nullable()->after('detail');
            }
        });
    }

    public function down(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            if (Schema::hasColumn('berkas', 'keywords')) {
                $table->dropColumn('keywords');
            }
        });
    }
};

