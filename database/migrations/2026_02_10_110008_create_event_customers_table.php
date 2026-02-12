<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_customers', function (Blueprint $table) {
            $table->id();
            
            // Kolom visual & identitas
            $table->string('thumbnail')->nullable();
            $table->string('cust_name')->index(); // Di-index agar filter cepat
            $table->string('model')->nullable()->index();
            
            // Data Utama
            $table->string('kode_berkas'); // Part No
            $table->string('nama');        // Part Name
            $table->string('detail');      // Detail Audit
            
            // Pencarian & Tagging (TagsInput menyimpan array)
            $table->json('keywords')->nullable(); 
            
            // File
            $table->string('dokumen')->nullable();     // File PDF/Utama
            $table->string('dokumen_src')->nullable(); // File Source (Admin only)
            
            // Status & System
            $table->boolean('is_public')->default(false); // Untuk query scope public
            $table->timestamps();
            
            // Opsional: Constraint unik gabungan (sesuai logic validation di resource)
            // $table->unique(['kode_berkas', 'detail']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_customers');
    }
};