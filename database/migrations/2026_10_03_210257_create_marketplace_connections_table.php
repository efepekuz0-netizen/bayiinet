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
        Schema::create('marketplace_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->string('name');
            $table->string('account_id');
            $table->longText('credentials');
            $table->longText('options')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->text('orders_cursor')->nullable();
            $table->timestamp('orders_window_start')->nullable();
            $table->timestamp('orders_window_end')->nullable();
            $table->boolean('orders_has_more')->default(false);
            $table->timestamp('last_orders_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_connections');
    }
};
