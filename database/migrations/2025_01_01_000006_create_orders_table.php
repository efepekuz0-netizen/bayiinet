<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();

            // Müşteri bilgileri (kör kargo için)
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_city');
            $table->string('customer_district')->nullable();
            $table->text('customer_address');
            $table->string('customer_email')->nullable();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->enum('status', [
                'pending',          // Bayi girdi, ödeme bekleniyor
                'paid',             // Ödendi, hazırlanacak
                'preparing',        // Paketleniyor
                'shipped',          // Kargoya verildi
                'delivered',        // Teslim edildi
                'cancelled',
                'returned',
            ])->default('pending');

            $table->string('cargo_company')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('dealer_note')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
