<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('imm_manual_mutu', function (Blueprint $table) {
            $table->id();

            // Kolom list
            $table->string('no', 100)->index();           // "No"
            $table->string('nama_dokumen', 255)->index();  // "Nama Dokumen"
            $table->json('keywords')->nullable();          // "Kata Kunci" (array/string)
            $table->string('file', 512)->nullable();       // path file terkini (untuk "Lihat File")

            // Riwayat revisi (untuk modal view)
            // Simpan array of objects: [{revision, filename, description, uploaded_at, replaced_at, file_size, file_ext}, ...]
            $table->json('versions')->nullable();

            // Optional metadata (kalau butuh ke depan)
            $table->string('current_revision', 20)->nullable();
            $table->date('effective_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imm_manual_mutu');
    }
};
