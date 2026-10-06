<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            $table->string('stock_code');
            $table->string('product_title');
            $table->string('variant_text')->nullable();      // "Beden: XL / Renk: Siyah"
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);            // O anki bayi fiyatı
            $table->decimal('total_price', 12, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
