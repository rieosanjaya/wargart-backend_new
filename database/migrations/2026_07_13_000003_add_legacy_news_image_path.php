<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'legacy_image_path')) {
                $table->string('legacy_image_path', 500)->nullable()->after('image_media_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            if (Schema::hasColumn('news', 'legacy_image_path')) {
                $table->dropColumn('legacy_image_path');
            }
        });
    }
};
