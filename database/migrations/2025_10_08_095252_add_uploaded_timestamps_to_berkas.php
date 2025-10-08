<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('berkas', function (Blueprint $t) {
            if (!Schema::hasColumn('berkas', 'dokumen_uploaded_at')) {
                $t->timestamp('dokumen_uploaded_at')->nullable()->after('dokumen');
            }
            if (!Schema::hasColumn('berkas', 'dokumen_src_uploaded_at')) {
                $t->timestamp('dokumen_src_uploaded_at')->nullable()->after('dokumen_src');
            }
        });
    }

    public function down(): void
    {
        Schema::table('berkas', function (Blueprint $t) {
            if (Schema::hasColumn('berkas', 'dokumen_uploaded_at')) {
                $t->dropColumn('dokumen_uploaded_at');
            }
            if (Schema::hasColumn('berkas', 'dokumen_src_uploaded_at')) {
                $t->dropColumn('dokumen_src_uploaded_at');
            }
        });
    }
};
