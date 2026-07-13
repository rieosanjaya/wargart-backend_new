<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7)->unique();
            $table->decimal('rt_fee', 12, 2)->default(0);
            $table->decimal('waste_fee', 12, 2)->default(0);
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'CLOSED'])->default('DRAFT')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('dues_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_period_id')->constrained('billing_periods')->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained('residents')->restrictOnDelete();
            $table->decimal('rt_fee', 12, 2);
            $table->decimal('waste_fee', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->enum('status', ['UNPAID', 'PAID', 'VOID'])->default('UNPAID')->index();
            $table->date('due_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['billing_period_id', 'resident_id']);
            $table->index(['resident_id', 'status']);
        });

        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dues_bill_id')->constrained('dues_bills')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['CASH', 'TRANSFER', 'OTHER'])->default('CASH');
            $table->string('reference', 120)->nullable()->index();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('paid_at');
            $table->timestamps();
            $table->index(['dues_bill_id', 'paid_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60)->index();
            $table->string('title', 180);
            $table->text('body');
            $table->json('data_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('payment_records');
        Schema::dropIfExists('dues_bills');
        Schema::dropIfExists('billing_periods');
    }
};
