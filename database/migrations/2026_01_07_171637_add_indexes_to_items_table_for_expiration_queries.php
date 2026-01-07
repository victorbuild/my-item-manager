<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        Schema::table('items', function (Blueprint $table) use ($driver) {

            if (!Schema::hasIndex('items', 'items_user_id_index')) {
                $table->index('user_id', 'items_user_id_index');
            }

            if (!Schema::hasIndex('items', 'idx_items_expiration_date')) {
                if ($driver === 'pgsql') {
                    DB::statement('CREATE INDEX idx_items_expiration_date ON items (expiration_date) WHERE expiration_date IS NOT NULL');
                } else {
                    $table->index('expiration_date', 'idx_items_expiration_date');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        Schema::table('items', function (Blueprint $table) use ($driver) {
            if (Schema::hasIndex('items', 'idx_items_expiration_date')) {
                if ($driver === 'pgsql') {
                    DB::statement('DROP INDEX IF EXISTS idx_items_expiration_date');
                } else {
                    $table->dropIndex('idx_items_expiration_date');
                }
            }

            if (Schema::hasIndex('items', 'items_user_id_index')) {
                $table->dropIndex('items_user_id_index');
            }
        });
    }
};
