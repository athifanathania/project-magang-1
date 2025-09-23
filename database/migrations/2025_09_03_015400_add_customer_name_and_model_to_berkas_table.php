<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            $table->string('cust_name', 100)->nullable()->after('id');
            $table->string('model', 100)->nullable()->after('cust_name');
        });
    }

    public function down(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            $table->dropColumn(['cust_name', 'model']);
        });
    }
};
