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
            $table->string('status')
                ->default('draft')
                ->after('original_extension')
                ->comment('圖片狀態：draft 僅上傳未被關聯、used 已關聯至 item');

            $table->unsignedInteger('usage_count')
                ->default(0)
                ->after('status')
                ->comment('被多少個 item 使用，作為刪除防呆依據');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_images', function (Blueprint $table) {
            $table->dropColumn(['status', 'usage_count']);
        });
    }
};
