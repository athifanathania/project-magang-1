<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Catatan:
         * - Di 'berkas' kita pakai pasangan: dokumen_src, dokumen_src_versions
         * - Di tabel lain (lampiran & IMM*) kita pakai: file_src, file_src_versions
         * - Semua kolom nullable; tidak menyentuh data lama.
         */

        // 1) BERKAS (regular)
        if (Schema::hasTable('berkas')) {
            Schema::table('berkas', function (Blueprint $table) {
                if (! Schema::hasColumn('berkas', 'dokumen_src')) {
                    $table->string('dokumen_src')->nullable();
                }
                if (! Schema::hasColumn('berkas', 'dokumen_src_versions')) {
                    $table->json('dokumen_src_versions')->nullable();
                }
            });
        }

        // 2) LAMPIRAN REGULER
        if (Schema::hasTable('lampirans')) {
            Schema::table('lampirans', function (Blueprint $table) {
                if (! Schema::hasColumn('lampirans', 'file_src')) {
                    $table->string('file_src')->nullable();
                }
                if (! Schema::hasColumn('lampirans', 'file_src_versions')) {
                    $table->json('file_src_versions')->nullable();
                }
            });
        }

        // 3) IMM LAMPIRANS
        if (Schema::hasTable('imm_lampirans')) {
            Schema::table('imm_lampirans', function (Blueprint $table) {
                if (! Schema::hasColumn('imm_lampirans', 'file_src')) {
                    $table->string('file_src')->nullable();
                }
                if (! Schema::hasColumn('imm_lampirans', 'file_src_versions')) {
                    $table->json('file_src_versions')->nullable();
                }
            });
        }

        // 4) IMM DOKUMEN (manual_mutu, prosedur, instruksi_standar, formulir)
        foreach ([
            'imm_manual_mutu',
            'imm_prosedur',
            'imm_instruksi_standar',
            'imm_formulir',
        ] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    if (! Schema::hasColumn($tbl, 'file_src')) {
                        $table->string('file_src')->nullable();
                    }
                    if (! Schema::hasColumn($tbl, 'file_src_versions')) {
                        $table->json('file_src_versions')->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('berkas')) {
            Schema::table('berkas', function (Blueprint $table) {
                if (Schema::hasColumn('berkas', 'dokumen_src_versions')) {
                    $table->dropColumn('dokumen_src_versions');
                }
                if (Schema::hasColumn('berkas', 'dokumen_src')) {
                    $table->dropColumn('dokumen_src');
                }
            });
        }

        foreach ([
            'lampirans',
            'imm_lampirans',
            'imm_manual_mutu',
            'imm_prosedur',
            'imm_instruksi_standar',
            'imm_formulir',
        ] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    if (Schema::hasColumn($tbl, 'file_src_versions')) {
                        $table->dropColumn('file_src_versions');
                    }
                    if (Schema::hasColumn($tbl, 'file_src')) {
                        $table->dropColumn('file_src');
                    }
                });
            }
        }
    }
};
