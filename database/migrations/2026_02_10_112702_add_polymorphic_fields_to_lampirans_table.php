<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            
            // Cek: Kalau kolom parent_id BELUM ada, baru buat
            if (!Schema::hasColumn('lampirans', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            }

            // Cek: Kalau kolom parent_type BELUM ada, baru buat
            if (!Schema::hasColumn('lampirans', 'parent_type')) {
                $table->string('parent_type')->nullable()->after('parent_id');
            }
            
            // Tambahkan index agar pencarian cepat (opsional, tapi disarankan)
            // Kita bungkus try-catch biar gak error kalau index sudah ada
            try {
                $table->index(['parent_type', 'parent_id']);
            } catch (\Exception $e) {
                // Index sudah ada, abaikan
            }
        });
    }

    public function down(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            // Hapus kolom jika ada (untuk rollback)
            if (Schema::hasColumn('lampirans', 'parent_type')) {
                $table->dropColumn('parent_type');
            }
            if (Schema::hasColumn('lampirans', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
        });
    }
};