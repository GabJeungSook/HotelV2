<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE transactions MODIFY guest_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE transactions MODIFY room_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE transactions MODIFY floor_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transactions MODIFY guest_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE transactions MODIFY room_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE transactions MODIFY floor_id BIGINT UNSIGNED NOT NULL');
    }
};
