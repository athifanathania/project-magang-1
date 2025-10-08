<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $t) {
            // Cek dulu biar aman ketika migrate ulang
            if (! $this->hasIndex($t->getTable(), 'activity_log_created_at_index')) {
                $t->index('created_at');
            }
            if (! $this->hasIndex($t->getTable(), 'activity_log_log_name_event_index')) {
                $t->index(['log_name', 'event']);
            }
            if (! $this->hasIndex($t->getTable(), 'activity_log_subject_type_subject_id_index')) {
                $t->index(['subject_type', 'subject_id']);
            }
            if (! $this->hasIndex($t->getTable(), 'activity_log_causer_id_index')) {
                $t->index('causer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $t) {
            $t->dropIndex(['created_at']);
            $t->dropIndex(['log_name', 'event']);
            $t->dropIndex(['subject_type', 'subject_id']);
            $t->dropIndex(['causer_id']);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $sm->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        $indexes = $sm->listTableIndexes($table);
        return array_key_exists($indexName, $indexes);
    }
};
