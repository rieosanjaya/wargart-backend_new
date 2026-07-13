<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 80)->unique();
            $table->string('password_hash');
            $table->enum('role', ['ADMIN', 'CITIZEN'])->index();
            $table->string('phone_e164', 20)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->unsignedBigInteger('resident_id')->nullable()->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('family_card_number', 32)->unique();
            $table->text('address');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->timestamps();
        });

        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 32)->unique();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->string('full_name', 160)->index();
            $table->string('birth_place', 120)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['MALE', 'FEMALE'])->nullable();
            $table->text('address');
            $table->string('religion', 40)->nullable();
            $table->string('marital_status', 40)->nullable();
            $table->string('occupation', 120)->nullable();
            $table->string('nationality', 80)->default('Indonesia');
            $table->date('id_valid_until')->nullable();
            $table->string('family_relationship', 60)->nullable();
            $table->unsignedBigInteger('photo_media_id')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['household_id', 'is_active']);
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 40);
            $table->string('path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->foreign('photo_media_id')->references('id')->on('media_files')->nullOnDelete();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('resident_id')->references('id')->on('residents')->nullOnDelete();
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('refresh_token_hash', 64)->unique();
            $table->string('device_name', 120)->nullable();
            $table->enum('device_type', ['ANDROID', 'DESKTOP', 'OTHER'])->default('OTHER');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->dateTime('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at', 'expires_at']);
        });

        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('purpose', ['REGISTER', 'LOGIN', 'CHANGE_PHONE', 'RESET_PASSWORD']);
            $table->string('destination', 120);
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->dateTime('expires_at')->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->dateTime('last_sent_at');
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('user_sessions');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['resident_id']));
        Schema::table('residents', fn (Blueprint $table) => $table->dropForeign(['photo_media_id']));
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('residents');
        Schema::dropIfExists('households');
        Schema::dropIfExists('users');
    }
};
