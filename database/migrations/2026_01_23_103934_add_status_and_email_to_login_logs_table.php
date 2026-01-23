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
        Schema::table('login_logs', function (Blueprint $table) {
            // 修改 user_id 為可為 null（失敗登入時可能沒有 user_id）
            $table->foreignId('user_id')->nullable()->change();
            
            // 新增 email 欄位（記錄嘗試登入的 email，失敗時可能沒有 user_id）
            $table->string('email')->nullable()->after('user_id')->comment('嘗試登入的 Email');
            
            // 新增 status 欄位（success 或 failed）
            $table->enum('status', ['success', 'failed'])->default('success')->after('email')->comment('登入狀態：success=成功, failed=失敗');
            
            // 新增索引以提升查詢效能
            $table->index('status');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_logs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['email']);
            $table->dropColumn(['status', 'email']);
            // 注意：不還原 user_id 的 nullable，因為可能已有資料
        });
    }
};
