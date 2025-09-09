<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('department');
            $table->index(['department', 'is_active']);
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['department', 'is_active']);
            $table->dropColumn(['department', 'is_active']);
        });
    }
};
