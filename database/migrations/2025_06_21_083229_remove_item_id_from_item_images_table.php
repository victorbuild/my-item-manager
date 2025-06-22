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
        Schema::table('item_images', function (Blueprint $table) {
            // 先移除外鍵
            $table->dropForeign(['item_id']);
            // 再移除欄位
            $table->dropColumn('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_images', function (Blueprint $table) {
            // 還原欄位與外鍵
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
        });
    }
};
