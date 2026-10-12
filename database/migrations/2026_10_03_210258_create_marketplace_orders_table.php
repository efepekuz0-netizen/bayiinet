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
        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_connection_id')->constrained()->cascadeOnDelete();
            $table->string('remote_package_id');
            $table->string('order_number')->nullable();
            $table->string('status')->default('Unknown');
            $table->string('cargo_company')->nullable();
            $table->string('cargo_tracking_number')->nullable();
            $table->text('cargo_tracking_link')->nullable();
            $table->string('invoice_number')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->longText('payload');
            $table->timestamps();

            $table->unique(['marketplace_connection_id', 'remote_package_id']);
            $table->index(['marketplace_connection_id', 'status', 'ordered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};
