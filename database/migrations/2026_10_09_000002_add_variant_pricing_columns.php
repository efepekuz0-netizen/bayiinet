<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ProductVariant modelinde tanımlı olup migration'da hiç oluşturulmamış kolonlar.
 * Bu yüzden varyant fiyatı hiçbir zaman saklanamıyor, XML feed'inde ve
 * Trendyol senkronunda varyant fiyatı hep 0/boş kalıyordu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'variant_price')) {
                $table->decimal('variant_price', 12, 2)->nullable()->after('value');
            }
            if (! Schema::hasColumn('product_variants', 'variant_stock')) {
                $table->unsignedInteger('variant_stock')->nullable()->after('stock');
            }
            if (! Schema::hasColumn('product_variants', 'variant_images')) {
                $table->json('variant_images')->nullable()->after('price_diff');
            }
        });

        if (! Schema::hasIndex('product_variants', 'product_variants_barcode_index')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->index('barcode');
            });
        }
    }

    public function down(): void
    {
        // Kolonlar geri alınmaz (üretim verisi korunur).
    }
};
