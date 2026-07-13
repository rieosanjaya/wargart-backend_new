<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Desktop lama memperlakukan no_kk sebagai teks dari form/query lama.
        // Karena itu backend baru tidak boleh menolak data hanya karena formatnya
        // tidak persis 16 digit.
        foreach ([
            "ALTER TABLE households DROP CHECK chk_households_kk_digits",
            "ALTER TABLE households DROP CONSTRAINT chk_households_kk_digits",
        ] as $statement) {
            try {
                DB::statement($statement);
                break;
            } catch (Throwable $ignored) {
                // MySQL/MariaDB berbeda sintaks dan constraint mungkin sudah tidak ada.
            }
        }
    }

    public function down(): void
    {
        // Tidak dipasang ulang agar rollback tidak gagal jika sudah ada data legacy.
    }
};
