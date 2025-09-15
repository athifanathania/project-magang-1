<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('imm_lampirans', function (Blueprint $table) {
            $table->id();

            $table->morphs('documentable'); // sudah termasuk index

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('imm_lampirans')
                ->nullOnDelete();

            $table->string('nama');
            $table->string('file')->nullable();
            $table->json('keywords')->nullable();
            $table->json('file_versions')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imm_lampirans');
    }
};
