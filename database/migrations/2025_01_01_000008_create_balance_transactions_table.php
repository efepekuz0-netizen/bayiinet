<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('type', ['deposit', 'order_payment', 'refund', 'adjustment']);
            $table->decimal('amount', 12, 2);                // + veya -
            $table->decimal('balance_after', 12, 2);
            $table->string('description')->nullable();
            $table->string('reference')->nullable();         // Banka dekont no vb.

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_transactions');
    }
};
