<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Yarım kalmış migration'lar yüzünden product_variants tablosu hiç
 * oluşmamış ya da eksik kolonlarla oluşmuş olabiliyor. Bu durumda
 * varyant stok toplama sorgusu (Product::effective_stock) patlıyor ve
 * anasayfa 500 veriyor. Bu migration eksikleri tamamlar; her kolon tek tek
 * ve hataya dayanıklı şekilde eklenir, tekrar tekrar çalıştırılabilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Tablo hiç yoksa sıfırdan kur.
        if (! Schema::hasTable('product_variants')) {
            Schema::create('product_variants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('barcode', 64)->nullable();
                $table->string('sku', 64)->nullable();
                $table->string('name')->nullable();
                $table->string('value')->nullable();
                $table->string('color')->nullable();
                $table->unsignedInteger('stock')->default(0);
                $table->decimal('price_diff', 10, 2)->default(0);
                $table->decimal('variant_price', 12, 2)->nullable();
                $table->unsignedInteger('variant_stock')->nullable();
                $table->json('variant_images')->nullable();
                $table->json('extra')->nullable();
                $table->timestamps();

                $table->index('product_id');
                $table->index('barcode');
            });
        }

        // 2) Eksik kolonları tamamla.
        $variantColumns = [
            'product_id' => fn (Blueprint $t) => $t->unsignedBigInteger('product_id')->nullable(),
            'barcode' => fn (Blueprint $t) => $t->string('barcode', 64)->nullable(),
            'sku' => fn (Blueprint $t) => $t->string('sku', 64)->nullable(),
            'name' => fn (Blueprint $t) => $t->string('name')->nullable(),
            'value' => fn (Blueprint $t) => $t->string('value')->nullable(),
            'color' => fn (Blueprint $t) => $t->string('color')->nullable(),
            'stock' => fn (Blueprint $t) => $t->unsignedInteger('stock')->default(0),
            'price_diff' => fn (Blueprint $t) => $t->decimal('price_diff', 10, 2)->default(0),
            'variant_price' => fn (Blueprint $t) => $t->decimal('variant_price', 12, 2)->nullable(),
            'variant_stock' => fn (Blueprint $t) => $t->unsignedInteger('variant_stock')->nullable(),
            'variant_images' => fn (Blueprint $t) => $t->json('variant_images')->nullable(),
            'extra' => fn (Blueprint $t) => $t->json('extra')->nullable(),
        ];

        foreach ($variantColumns as $column => $definition) {
            if (Schema::hasColumn('product_variants', $column)) {
                continue;
            }

            try {
                Schema::table('product_variants', function (Blueprint $table) use ($column, $definition) {
                    $definition($table);
                });
            } catch (Throwable $e) {
                // Tek kolon eklenemezse migration'ın tamamı patlamasın.
            }
        }

        foreach (['product_id', 'barcode'] as $indexed) {
            if (! Schema::hasColumn('product_variants', $indexed)) {
                continue;
            }

            $indexName = 'product_variants_'.$indexed.'_index';
            if (! Schema::hasIndex('product_variants', $indexName)) {
                try {
                    Schema::table('product_variants', function (Blueprint $table) use ($indexed) {
                        $table->index($indexed);
                    });
                } catch (Throwable $e) {
                    // indeks zaten varsa sorun değil
                }
            }
        }

        // 3) products tarafında anasayfa/kart için gereken kolonlar.
        if (Schema::hasTable('products')) {
            $productColumns = [
                'has_variants' => fn (Blueprint $t) => $t->boolean('has_variants')->default(false),
                'is_featured' => fn (Blueprint $t) => $t->boolean('is_featured')->default(false),
                'show_on_homepage' => fn (Blueprint $t) => $t->boolean('show_on_homepage')->nullable(),
                'last_synced_at' => fn (Blueprint $t) => $t->timestamp('last_synced_at')->nullable(),
                'sell_price' => fn (Blueprint $t) => $t->decimal('sell_price', 12, 2)->nullable(),
                'main_category' => fn (Blueprint $t) => $t->string('main_category')->nullable(),
                'category_path' => fn (Blueprint $t) => $t->string('category_path')->nullable(),
                'images' => fn (Blueprint $t) => $t->json('images')->nullable(),
            ];

            foreach ($productColumns as $column => $definition) {
                if (Schema::hasColumn('products', $column)) {
                    continue;
                }

                try {
                    Schema::table('products', function (Blueprint $table) use ($column, $definition) {
                        $definition($table);
                    });
                } catch (Throwable $e) {
                    // Tek kolon eklenemezse migration'ın tamamı patlamasın.
                }
            }
        }
    }

    public function down(): void
    {
        // Kolonlar geri alınmaz (üretim verisi korunur).
    }
};
