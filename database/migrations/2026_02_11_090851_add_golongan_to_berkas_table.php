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
        Schema::table('berkas', function (Blueprint $table) {
            // Menambahkan kolom 'golongan'
            // nullable() = agar aman jika ada data lama
            // default('New Model') = data lama otomatis dianggap New Model (opsional)
            // after('cust_name') = posisi kolom ditaruh setelah cust_name (biar rapi di database)
            
            $table->string('golongan')
                ->nullable()
                ->default('New Model') 
                ->after('cust_name'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('berkas', function (Blueprint $table) {
            // Menghapus kolom jika rollback
            $table->dropColumn('golongan');
        });
    }
};