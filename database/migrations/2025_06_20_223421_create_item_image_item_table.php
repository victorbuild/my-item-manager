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
        Schema::create('item_image_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->uuid('item_image_uuid');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序用');
            $table->timestamps();

            $table->unique(['item_id', 'item_image_uuid']);
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('item_image_uuid')->references('uuid')->on('item_images')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_image_item');
    }
};
