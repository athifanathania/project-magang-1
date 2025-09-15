<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $tables = [
        'imm_manual_mutu'        => ['drops' => ['revision','effective_at','expires_at','scope','approved_by','owner_id']],
        'imm_prosedur'           => ['drops' => ['revision','effective_at','expires_at','department','procedure_owner','owner_id']],
        'imm_instruksi_standar'  => ['drops' => ['revision','effective_at','expires_at','work_center','process_name','tools','owner_id']],
        'imm_formulir'           => ['drops' => ['revision','effective_at','expires_at','is_active','owner_department','owner_id']],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $conf) {
            // --- rename judul & nomor ke format final ---
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'number') && !Schema::hasColumn($table, 'no')) {
                    $t->renameColumn('number', 'no');
                }
                if (Schema::hasColumn($table, 'title') && !Schema::hasColumn($table, 'nama_dokumen')) {
                    $t->renameColumn('title', 'nama_dokumen');
                }

                // pastikan kolom inti ada
                if (!Schema::hasColumn($table, 'file'))          $t->string('file', 512)->nullable();
                if (!Schema::hasColumn($table, 'keywords'))      $t->json('keywords')->nullable();
                if (!Schema::hasColumn($table, 'file_versions')) $t->json('file_versions')->nullable(); // riwayat
            });

            // --- hapus kolom yang tidak dipakai di list/riwayat ---
            $toDrop = array_values(array_filter($conf['drops'] ?? [], fn ($col) => Schema::hasColumn($table, $col)));
            if (!empty($toDrop)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($toDrop));
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'no') && !Schema::hasColumn($table, 'number')) {
                    $t->renameColumn('no', 'number');
                }
                if (Schema::hasColumn($table, 'nama_dokumen') && !Schema::hasColumn($table, 'title')) {
                    $t->renameColumn('nama_dokumen', 'title');
                }
                // biarkan keywords & file_versions tetap (tidak perlu di-drop saat rollback)
            });
        }
    }
};
