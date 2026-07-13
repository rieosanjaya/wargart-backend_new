<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('letter_requests', 'legacy_file_path')) {
                $table->string('legacy_file_path', 500)->nullable()->after('result_media_id');
            }
            if (!Schema::hasColumn('letter_requests', 'legacy_file_name')) {
                $table->string('legacy_file_name', 255)->nullable()->after('legacy_file_path');
            }
            if (!Schema::hasColumn('letter_requests', 'legacy_generated_at')) {
                $table->timestamp('legacy_generated_at')->nullable()->after('legacy_file_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('letter_requests', function (Blueprint $table) {
            foreach (['legacy_generated_at', 'legacy_file_name', 'legacy_file_path'] as $column) {
                if (Schema::hasColumn('letter_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
