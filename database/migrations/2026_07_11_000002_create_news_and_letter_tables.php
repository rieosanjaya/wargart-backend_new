<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->longText('content');
            $table->foreignId('image_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'ARCHIVED'])->default('DRAFT')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        Schema::create('letter_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            $table->string('letter_type', 120);
            $table->text('purpose');
            $table->enum('status', ['SUBMITTED', 'IN_REVIEW', 'APPROVED', 'REJECTED', 'COMPLETED'])
                ->default('SUBMITTED')->index();
            $table->text('admin_note')->nullable();
            $table->string('letter_number', 120)->nullable()->unique();
            $table->foreignId('result_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['resident_id', 'submitted_at']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_requests');
        Schema::dropIfExists('news');
    }
};
