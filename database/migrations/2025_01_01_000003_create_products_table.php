<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('stock_code')->index();           // Benzersiz stok kodu
            $table->string('barcode')->nullable()->index();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->string('main_category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('category_path')->nullable();     // XML'den gelen ham kategori

            $table->decimal('price', 12, 2);                 // Bayi alış (standart) fiyat
            $table->decimal('list_price', 12, 2)->nullable(); // Tavsiye edilen satış
            $table->unsignedTinyInteger('tax_rate')->default(10); // KDV %
            $table->decimal('desi', 8, 2)->default(1);

            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('has_variants')->default(false);

            $table->json('images')->nullable();              // ["url1", "url2"]
            $table->json('attributes')->nullable();         // ekstra özellikler

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_code', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
