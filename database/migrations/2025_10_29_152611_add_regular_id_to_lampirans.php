<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->unsignedBigInteger('regular_id')->nullable()->after('berkas_id');
            $table->index('regular_id');
        });
    }

    public function down(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->dropIndex(['regular_id']);
            $table->dropColumn('regular_id');
        });
    }
};
