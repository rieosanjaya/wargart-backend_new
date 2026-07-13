<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('citizen_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['WAITING', 'ACTIVE', 'CLOSED'])->default('WAITING')->index();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('closed_at')->nullable()->index();
            $table->timestamps();
            $table->index(['citizen_user_id', 'status']);
            $table->index(['assigned_admin_id', 'status']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('message_type', ['TEXT', 'FILE'])->default('TEXT');
            $table->text('body')->nullable();
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->timestamp('sent_at')->useCurrent()->index();
            $table->timestamp('read_at')->nullable();
            $table->index(['chat_session_id', 'sent_at']);
        });

        Schema::create('admin_presence', function (Blueprint $table) {
            $table->foreignId('admin_user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['OFFLINE', 'ONLINE', 'BUSY'])->default('OFFLINE')->index();
            $table->unsignedSmallInteger('active_chat_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('entity_type', 120);
            $table->string('entity_id', 64);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['entity_type', 'entity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('admin_presence');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
