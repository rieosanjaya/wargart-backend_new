<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = [
        'users', 'households', 'residents', 'media_files', 'user_sessions', 'otp_challenges',
        'news', 'letter_requests', 'billing_periods', 'dues_bills', 'payment_records',
        'notifications', 'chat_sessions', 'chat_messages', 'admin_presence', 'audit_logs',
    ];

    public function test_all_domain_tables_exist_after_migration_from_empty_database(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_duplicate_nik_is_rejected(): void
    {
        $row = [
            'nik' => '3273010101010001',
            'full_name' => 'Test Resident',
            'address' => 'Test Address',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('residents')->insert($row);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('residents')->insert($row);
    }

    public function test_bill_total_must_equal_fee_components(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('CHECK constraint ini diverifikasi pada MySQL 8.');
        }
        $adminId = DB::table('users')->insertGetId([
            'username' => 'schema-admin', 'password_hash' => 'test-hash', 'role' => 'ADMIN',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $residentId = DB::table('residents')->insertGetId([
            'nik' => '3273010101010002', 'full_name' => 'Schema Resident', 'address' => 'Test',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $periodId = DB::table('billing_periods')->insertGetId([
            'period' => '2026-07', 'rt_fee' => 50000, 'waste_fee' => 25000,
            'status' => 'DRAFT', 'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('dues_bills')->insert([
            'billing_period_id' => $periodId, 'resident_id' => $residentId,
            'rt_fee' => 50000, 'waste_fee' => 25000, 'total_amount' => 1,
            'status' => 'UNPAID', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
