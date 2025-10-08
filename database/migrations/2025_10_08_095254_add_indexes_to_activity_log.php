<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function hasIndex(string $table, string $index): bool
    {
        // Works on MySQL/MariaDB
        $res = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
        return !empty($res);
    }

    public function up(): void
    {
        // created_at
        if (! $this->hasIndex('activity_log', 'activity_log_created_at_index')) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->index('created_at', 'activity_log_created_at_index');
            });
        }

        // log_name + event
        if (! $this->hasIndex('activity_log', 'activity_log_logname_event_index')) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->index(['log_name', 'event'], 'activity_log_logname_event_index');
            });
        }

        // subject_type + subject_id
        if (! $this->hasIndex('activity_log', 'activity_log_subject_index')) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->index(['subject_type', 'subject_id'], 'activity_log_subject_index');
            });
        }

        // causer_id (opsional: bisa juga tambah causer_type kalau perlu)
        if (! $this->hasIndex('activity_log', 'activity_log_causer_id_index')) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->index('causer_id', 'activity_log_causer_id_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('activity_log', 'activity_log_created_at_index')) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->dropIndex('activity_log_created_at_index');
            });
        }

        if ($this->hasIndex('activity_log', 'activity_log_logname_event_index')) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->dropIndex('activity_log_logname_event_index');
            });
        }

        if ($this->hasIndex('activity_log', 'activity_log_subject_index')) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->dropIndex('activity_log_subject_index');
            });
        }

        if ($this->hasIndex('activity_log', 'activity_log_causer_id_index')) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->dropIndex('activity_log_causer_id_index');
            });
        }
    }
};
