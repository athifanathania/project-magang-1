<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('imm_audit_internals', function (Blueprint $t) {
            $t->id();
            $t->string('departemen');
            $t->unsignedTinyInteger('semester'); // 1 atau 2
            $t->unsignedSmallInteger('tahun');   // periode
            // $t->unique(['departemen','semester','tahun']); // <- aktifkan kalau memang harus unik
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('imm_audit_internals');
    }
};
