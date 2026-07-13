<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        DB::statement("ALTER TABLE residents ADD CONSTRAINT chk_residents_nik_digits CHECK (nik REGEXP '^[0-9]{16}$')");
        DB::statement("ALTER TABLE households ADD CONSTRAINT chk_households_kk_digits CHECK (family_card_number REGEXP '^[0-9]{16}$')");
        DB::statement('ALTER TABLE otp_challenges ADD CONSTRAINT chk_otp_attempts CHECK (attempts <= max_attempts AND max_attempts > 0)');
        DB::statement('ALTER TABLE media_files ADD CONSTRAINT chk_media_size CHECK (size_bytes > 0)');
        DB::statement('ALTER TABLE billing_periods ADD CONSTRAINT chk_period_format CHECK (period REGEXP "^[0-9]{4}-(0[1-9]|1[0-2])$")');
        DB::statement('ALTER TABLE billing_periods ADD CONSTRAINT chk_period_fees CHECK (rt_fee >= 0 AND waste_fee >= 0)');
        DB::statement('ALTER TABLE dues_bills ADD CONSTRAINT chk_bill_amounts CHECK (rt_fee >= 0 AND waste_fee >= 0 AND total_amount = rt_fee + waste_fee)');
        DB::statement('ALTER TABLE payment_records ADD CONSTRAINT chk_payment_amount CHECK (amount > 0)');
        DB::statement("ALTER TABLE chat_messages ADD CONSTRAINT chk_chat_message_content CHECK ((message_type = 'TEXT' AND body IS NOT NULL AND CHAR_LENGTH(TRIM(body)) > 0) OR (message_type = 'FILE' AND media_file_id IS NOT NULL))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        DB::statement('ALTER TABLE chat_messages DROP CHECK chk_chat_message_content');
        DB::statement('ALTER TABLE payment_records DROP CHECK chk_payment_amount');
        DB::statement('ALTER TABLE dues_bills DROP CHECK chk_bill_amounts');
        DB::statement('ALTER TABLE billing_periods DROP CHECK chk_period_fees');
        DB::statement('ALTER TABLE billing_periods DROP CHECK chk_period_format');
        DB::statement('ALTER TABLE media_files DROP CHECK chk_media_size');
        DB::statement('ALTER TABLE otp_challenges DROP CHECK chk_otp_attempts');
        DB::statement('ALTER TABLE households DROP CHECK chk_households_kk_digits');
        DB::statement('ALTER TABLE residents DROP CHECK chk_residents_nik_digits');
    }
};
