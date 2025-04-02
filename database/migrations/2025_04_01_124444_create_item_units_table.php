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
        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->onDelete('cascade')->comment('對應 items 表');
            $table->unsignedInteger('unit_number')->comment('同一 item 中第幾件（1, 2, 3...）');
            $table->timestamp('used_at')->nullable()->comment('開始使用時間');
            $table->timestamp('discarded_at')->nullable()->comment('丟棄時間');
            $table->text('notes')->nullable()->comment('備註');
            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_units');
    }
};
