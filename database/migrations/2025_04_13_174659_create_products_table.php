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
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('自動遞增主鍵，僅限內部使用');
            $table->uuid('uuid')->unique()->comment('UUID');
            $table->string('short_id', 20)->unique()->comment('short ID');

            $table->unsignedBigInteger('user_id')->comment('建立產品的使用者 ID');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedBigInteger('category_id')->nullable()->comment('所屬分類');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');

            $table->string('name')->comment('產品名稱');
            $table->string('brand')->nullable()->comment('品牌名稱');
            $table->string('model')->nullable()->comment('產品型號');
            $table->string('spec')->nullable()->comment('產品規格');
            $table->string('barcode')->nullable()->comment('產品條碼，可用於掃描比對');
            $table->timestamps();

            $table->comment('產品定義表，用於統一管理 item 對應的基本產品資訊');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
