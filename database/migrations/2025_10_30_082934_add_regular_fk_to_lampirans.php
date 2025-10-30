<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Helper cek index & constraint
        $idxExists = function (string $indexName): bool {
            $sql = "SELECT 1 FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = 'lampirans'
                      AND index_name = ?";
            return (bool) DB::selectOne($sql, [$indexName]);
        };

        $fkExists = function (string $fkName): bool {
            $sql = "SELECT 1 FROM information_schema.referential_constraints
                    WHERE constraint_schema = DATABASE()
                      AND table_name = 'lampirans'
                      AND constraint_name = ?";
            return (bool) DB::selectOne($sql, [$fkName]);
        };

        $checkExists = function (string $checkName): bool {
            $sql = "SELECT 1 FROM information_schema.table_constraints
                    WHERE constraint_schema = DATABASE()
                      AND table_name = 'lampirans'
                      AND constraint_type = 'CHECK'
                      AND constraint_name = ?";
            return (bool) DB::selectOne($sql, [$checkName]);
        };

        // 1) Pastikan kolom ADA; kalau belum ada (edge case), baru tambahkan.
        if (! Schema::hasColumn('lampirans', 'regular_id')) {
            Schema::table('lampirans', function (Blueprint $t) {
                $t->unsignedBigInteger('regular_id')->nullable()->after('berkas_id');
                $t->index('regular_id');
            });
        } else {
            // (opsional) pastikan tipe kolom benar: BIGINT UNSIGNED NULL
            try {
                DB::statement("ALTER TABLE lampirans MODIFY regular_id BIGINT UNSIGNED NULL");
            } catch (\Throwable $e) {
                // abaikan jika tidak perlu / engine tidak mengizinkan
            }
        }

        // 2) Foreign key ke regulars (nullOnDelete), kalau belum ada
        $fkName = 'lampirans_regular_id_foreign';
        if (! $fkExists($fkName)) {
            Schema::table('lampirans', function (Blueprint $t) use ($fkName) {
                $t->foreign('regular_id', $fkName)
                  ->references('id')->on('regulars')
                  ->nullOnDelete();
            });
        }

        // 3) Index gabungan (performa & query anak)
        $idxRegPar  = 'lampirans_regular_id_parent_id_index';
        $idxBerPar  = 'lampirans_berkas_id_parent_id_index';

        if (! $idxExists($idxRegPar)) {
            Schema::table('lampirans', function (Blueprint $t) use ($idxRegPar) {
                $t->index(['regular_id', 'parent_id'], $idxRegPar);
            });
        }
        if (! $idxExists($idxBerPar)) {
            Schema::table('lampirans', function (Blueprint $t) use ($idxBerPar) {
                $t->index(['berkas_id', 'parent_id'], $idxBerPar);
            });
        }

        // 4) CHECK constraint: exactly-one-owner (jika MySQL 8.0+ dan belum ada)
        $chk = 'chk_lampirans_owner';
        if (! $checkExists($chk)) {
            try {
                DB::statement("
                    ALTER TABLE lampirans
                    ADD CONSTRAINT {$chk}
                    CHECK (
                        (berkas_id IS NOT NULL AND regular_id IS NULL)
                     OR (berkas_id IS NULL AND regular_id IS NOT NULL)
                    )
                ");
            } catch (\Throwable $e) {
                // abaikan jika engine tidak mendukung CHECK (MariaDB lama)
            }
        }
    }

    public function down(): void
    {
        // Catatan: kita TIDAK menghapus kolom regular_id karena sudah ada sebelum migrasi ini.
        // Kita hanya balikkan perubahan yang kita tambahkan di up().

        $dropIfExists = function (string $sql) {
            try { DB::statement($sql); } catch (\Throwable $e) {}
        };

        // Drop CHECK jika ada
        $this->dropCheckIfExists('chk_lampirans_owner');

        // Drop FK jika ada
        try {
            Schema::table('lampirans', function (Blueprint $t) {
                $t->dropForeign('lampirans_regular_id_foreign');
            });
        } catch (\Throwable $e) {}

        // Drop index gabungan jika ada
        try {
            Schema::table('lampirans', function (Blueprint $t) {
                $t->dropIndex('lampirans_regular_id_parent_id_index');
            });
        } catch (\Throwable $e) {}
        try {
            Schema::table('lampirans', function (Blueprint $t) {
                $t->dropIndex('lampirans_berkas_id_parent_id_index');
            });
        } catch (\Throwable $e) {}
        // (index single 'regular_id' biarkan, karena bisa jadi dibuat oleh migrasi lama)
    }

    private function dropCheckIfExists(string $name): void
    {
        try {
            $sql = "SELECT 1 FROM information_schema.table_constraints
                    WHERE constraint_schema = DATABASE()
                      AND table_name = 'lampirans'
                      AND constraint_type = 'CHECK'
                      AND constraint_name = ?";
            if (DB::selectOne($sql, [$name])) {
                DB::statement("ALTER TABLE lampirans DROP CONSTRAINT {$name}");
            }
        } catch (\Throwable $e) {
            // engine lama: nothing to do
        }
    }
};
