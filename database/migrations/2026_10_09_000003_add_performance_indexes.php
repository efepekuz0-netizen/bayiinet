<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liste / filtre / senkron sorgularını hızlandıran ek indeksler.
 * (Kategori ve ürün listeleri on binlerce kayıtta tam tarama yapıyordu.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $this->addIndex('products', ['is_active', 'last_synced_at'], 'products_active_synced_index');
            $this->addIndex('products', ['is_active', 'main_category'], 'products_active_category_index');
            $this->addIndex('products', ['source_id'], 'products_source_id_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $this->addIndex('orders', ['dealer_id', 'status'], 'orders_dealer_status_index');
        });

        Schema::table('dealer_trendyol_listings', function (Blueprint $table) {
            $this->addIndex('dealer_trendyol_listings', ['dealer_id', 'status'], 'dtl_dealer_status_index');
            $this->addIndex('dealer_trendyol_listings', ['product_id'], 'dtl_product_id_index');
        });
    }

    public function down(): void
    {
        // İndeksler geri alınmaz (üretim verisi korunur).
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $table) use ($columns, $name): void {
                $table->index($columns, $name);
            });
        }
    }
};
