<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('barcode')->nullable()->index();
            $table->string('sku')->nullable();
            $table->string('name');                          // "Beden", "Renk" vb.
            $table->string('value');                         // "XL", "Siyah"
            $table->string('color')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('price_diff', 10, 2)->default(0); // ana fiyata ek

            $table->json('extra')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
