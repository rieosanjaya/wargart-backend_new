<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoDataSeeder dilarang di production.');
        }

        $adminPassword = (string) (env('DEMO_ADMIN_PASSWORD') ?: 'AdminDemo@12345');
        $citizenPassword = (string) (env('DEMO_CITIZEN_PASSWORD') ?: 'rio123');
        $adminPhone = (string) env('DEMO_ADMIN_PHONE', '+6281234500099');
        if (strlen($adminPassword) < 12 || strlen($citizenPassword) < 6) {
            throw new RuntimeException('DEMO_ADMIN_PASSWORD minimal 12 karakter dan DEMO_CITIZEN_PASSWORD minimal 6 karakter.');
        }

        DB::transaction(function () use ($adminPassword, $citizenPassword, $adminPhone): void {
            $now = now();

            $householdId = DB::table('households')->updateOrInsert(
                ['family_card_number' => '3273010101010001'],
                ['address' => 'Jl. Contoh Warga No. 1', 'rt' => '001', 'rw' => '002', 'updated_at' => $now, 'created_at' => $now]
            );
            $householdId = DB::table('households')->where('family_card_number', '3273010101010001')->value('id');

            DB::table('residents')->updateOrInsert(
                ['nik' => '3273010101010001'],
                [
                    'household_id' => $householdId, 'full_name' => 'Budi Demo', 'birth_place' => 'Bandung',
                    'birth_date' => '1988-03-12', 'gender' => 'MALE', 'address' => 'Jl. Contoh Warga No. 1',
                    'religion' => 'Islam', 'marital_status' => 'Kawin', 'occupation' => 'Wiraswasta',
                    'nationality' => 'Indonesia', 'family_relationship' => 'Kepala Keluarga', 'is_active' => true,
                    'updated_at' => $now, 'created_at' => $now,
                ]
            );
            DB::table('residents')->updateOrInsert(
                ['nik' => '3273010202020002'],
                [
                    'household_id' => $householdId, 'full_name' => 'Sari Demo', 'birth_place' => 'Bandung',
                    'birth_date' => '1990-08-20', 'gender' => 'FEMALE', 'address' => 'Jl. Contoh Warga No. 1',
                    'religion' => 'Islam', 'marital_status' => 'Kawin', 'occupation' => 'Guru',
                    'nationality' => 'Indonesia', 'family_relationship' => 'Istri', 'is_active' => true,
                    'updated_at' => $now, 'created_at' => $now,
                ]
            );
            $residentId = DB::table('residents')->where('nik', '3273010101010001')->value('id');

            DB::table('users')->updateOrInsert(
                ['username' => 'admin.demo'],
                [
                    'password_hash' => Hash::make($adminPassword), 'role' => 'ADMIN',
                    'phone_e164' => $adminPhone, 'phone_verified_at' => $now, 'resident_id' => null, 'is_active' => true,
                    'updated_at' => $now, 'created_at' => $now,
                ]
            );
            $adminId = DB::table('users')->where('username', 'admin.demo')->value('id');

            DB::table('users')->where('username', 'warga.demo')->update(['username' => 'rio', 'updated_at' => $now]);
            DB::table('users')->updateOrInsert(
                ['username' => 'rio'],
                [
                    'password_hash' => Hash::make($citizenPassword), 'role' => 'CITIZEN',
                    'phone_e164' => '+6281234500001', 'phone_verified_at' => $now,
                    'resident_id' => $residentId, 'is_active' => true,
                    'updated_at' => $now, 'created_at' => $now,
                ]
            );
            $citizenId = DB::table('users')->where('username', 'rio')->value('id');

            DB::table('admin_presence')->updateOrInsert(
                ['admin_user_id' => $adminId],
                ['status' => 'OFFLINE', 'active_chat_count' => 0, 'updated_at' => $now, 'created_at' => $now]
            );

            DB::table('news')->updateOrInsert(
                ['title' => 'Kerja Bakti Lingkungan'],
                [
                    'content' => 'Kerja bakti demonstrasi dilaksanakan hari Minggu pukul 07.00.',
                    'status' => 'PUBLISHED', 'published_at' => $now, 'created_by' => $adminId,
                    'updated_by' => $adminId, 'updated_at' => $now, 'created_at' => $now,
                ]
            );

            DB::table('letter_requests')->updateOrInsert(
                ['public_id' => '01J00000000000000000000001'],
                [
                    'resident_id' => $residentId, 'letter_type' => 'Surat Pengantar Domisili',
                    'purpose' => 'Data demonstrasi pengajuan surat', 'status' => 'SUBMITTED',
                    'submitted_at' => $now, 'updated_at' => $now, 'created_at' => $now,
                ]
            );

            DB::table('billing_periods')->updateOrInsert(
                ['period' => '2026-07'],
                [
                    'rt_fee' => 50000, 'waste_fee' => 25000, 'status' => 'PUBLISHED',
                    'published_at' => $now, 'created_by' => $adminId, 'updated_at' => $now, 'created_at' => $now,
                ]
            );
            $periodId = DB::table('billing_periods')->where('period', '2026-07')->value('id');
            DB::table('dues_bills')->updateOrInsert(
                ['billing_period_id' => $periodId, 'resident_id' => $residentId],
                [
                    'rt_fee' => 50000, 'waste_fee' => 25000, 'total_amount' => 75000,
                    'status' => 'UNPAID', 'due_at' => '2026-07-20', 'updated_at' => $now, 'created_at' => $now,
                ]
            );

            DB::table('notifications')->updateOrInsert(
                ['id' => '01J00000000000000000000002'],
                [
                    'user_id' => $citizenId, 'type' => 'DUES_REMINDER', 'title' => 'Pengingat Iuran Juli 2026',
                    'body' => 'Tagihan demonstrasi sebesar Rp75.000 belum dibayar.',
                    'data_json' => json_encode(['period' => '2026-07']), 'created_at' => $now,
                ]
            );

            DB::table('chat_sessions')->updateOrInsert(
                ['id' => '01J00000000000000000000003'],
                [
                    'citizen_user_id' => $citizenId, 'assigned_admin_id' => null,
                    'status' => 'WAITING', 'started_at' => $now, 'updated_at' => $now, 'created_at' => $now,
                ]
            );
            DB::table('chat_messages')->updateOrInsert(
                ['id' => '01J00000000000000000000004'],
                [
                    'chat_session_id' => '01J00000000000000000000003', 'sender_user_id' => $citizenId,
                    'message_type' => 'TEXT', 'body' => 'Halo admin, ini pesan demonstrasi.', 'sent_at' => $now,
                ]
            );

            DB::table('audit_logs')->insert([
                'actor_user_id' => $adminId, 'action' => 'DEMO_DATA_SEEDED',
                'entity_type' => 'system', 'entity_id' => 'demo',
                'after_json' => json_encode(['version' => 1]), 'created_at' => $now,
            ]);
        });
    }
}
