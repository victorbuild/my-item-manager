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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->comment('UUID');
            $table->string('short_id', 11)->unique()->comment('網址id');
            $table->string('name')->comment('物品名稱');
            $table->string('barcode')->nullable()->index()->comment('商品條碼');
            $table->text('description')->nullable()->comment('物品描述');
            $table->string('location')->nullable()->comment('存放位置');
            $table->integer('quantity')->default(1)->comment('購買數量');
            $table->decimal('price', 10, 2)->nullable()->comment('總金額');
            $table->date('purchased_at')->nullable()->comment('購買日期');
            $table->boolean('is_discarded')->default(false)->comment('是否報廢');
            $table->timestamp('discarded_at')->nullable()->comment('報廢時間');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
