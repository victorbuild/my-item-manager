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
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('使用者 ID');
            $table->string('ip_address', 45)->nullable()->comment('登入 IP 位址');
            $table->text('user_agent')->nullable()->comment('使用者代理（瀏覽器資訊）');
            $table->timestamp('logged_in_at')->useCurrent()->comment('登入時間');
            $table->timestamps();

            $table->index('user_id');
            $table->index('logged_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
