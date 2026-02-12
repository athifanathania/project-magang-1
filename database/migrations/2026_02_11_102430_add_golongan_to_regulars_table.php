<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulars', function (Blueprint $table) {
            // Menambahkan kolom golongan, boleh null (untuk jaga-jaga), ditaruh setelah kolom 'nama'
            $table->string('golongan')->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('regulars', function (Blueprint $table) {
            $table->dropColumn('golongan');
        });
    }
};