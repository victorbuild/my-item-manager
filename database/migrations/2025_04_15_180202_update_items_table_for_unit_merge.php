<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('quantity');
            $table->timestamp('received_at')->nullable()->after('purchased_at');
            $table->timestamp('used_at')->nullable()->after('received_at');
            $table->text('notes')->nullable()->after('discarded_at');

            $table->string('serial_number')->nullable()->after('barcode')->comment('實體序號');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('location');

            $table->dropColumn('received_at');
            $table->dropColumn('used_at');
            $table->dropColumn('notes');
            $table->dropColumn('serial_number');
        });
    }
};
