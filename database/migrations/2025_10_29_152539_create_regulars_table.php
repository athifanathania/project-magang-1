<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regulars', function (Blueprint $table) {
            $table->id();
            $table->string('cust_name', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('kode_berkas');                 // Part No
            $table->string('nama');                        // Part Name
            $table->string('thumbnail')->nullable();
            $table->string('detail')->nullable()->default('Regular');
            $table->longText('keywords')->nullable();

            $table->string('dokumen')->nullable();
            $table->timestamp('dokumen_uploaded_at')->nullable();
            $table->json('dokumen_versions')->nullable();

            $table->boolean('is_public')->default(true);

            // file sumber (ikuti pola berkas)
            $table->string('dokumen_src')->nullable();
            $table->timestamp('dokumen_src_uploaded_at')->nullable();
            $table->json('dokumen_src_versions')->nullable();

            $table->timestamps();

            // index/unique sama pola berkas (gabungan PartNo + Detail)
            $table->unique(['kode_berkas', 'detail'], 'regulars_kode_detail_unique');

            // Kolom CI tersimpan jika kamu juga pakai di berkas
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->string('kode_berkas_ci', 191)->storedAs('LOWER(TRIM(`kode_berkas`))')->nullable();
                $table->string('detail_ci', 191)->storedAs('LOWER(TRIM(`detail`))')->nullable();
                $table->index(['kode_berkas_ci', 'detail_ci'], 'regulars_kode_detail_ci_index');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulars');
    }
};
