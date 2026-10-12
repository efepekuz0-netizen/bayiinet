<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            if (! Schema::hasColumn('dealers', 'trendyol_seller_id')) {
                $table->string('trendyol_seller_id')->nullable();
            }
            if (! Schema::hasColumn('dealers', 'trendyol_credentials')) {
                $table->longText('trendyol_credentials')->nullable(); // şifreli: api_key + api_secret
            }
            if (! Schema::hasColumn('dealers', 'trendyol_last_error')) {
                $table->text('trendyol_last_error')->nullable();
            }
        });

        Schema::create('dealer_trendyol_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('barcode', 64);
            $table->decimal('sale_price', 12, 2);
            $table->decimal('list_price', 12, 2);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();

            // pending: hazırlandı, sent: Trendyol'a gönderildi, created: Trendyol kabul etti, failed: hata
            $table->string('status', 20)->default('pending');
            $table->string('batch_request_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['dealer_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_trendyol_listings');
    }
};
