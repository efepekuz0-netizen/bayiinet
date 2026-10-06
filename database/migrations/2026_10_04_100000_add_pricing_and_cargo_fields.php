<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->nullable()->after('price'); // XML ham alış
            }
            if (!Schema::hasColumn('products', 'xml_margin_percent')) {
                $table->decimal('xml_margin_percent', 8, 2)->nullable()->after('cost_price'); // benim karım %
            }
            if (!Schema::hasColumn('products', 'sell_price')) {
                $table->decimal('sell_price', 12, 2)->nullable()->after('xml_margin_percent'); // bayilere satış (cost + margin)
            }
            if (!Schema::hasColumn('products', 'min_margin_percent')) {
                $table->decimal('min_margin_percent', 8, 2)->nullable()->after('sell_price');
            }
            if (!Schema::hasColumn('products', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('products', 'show_on_homepage')) {
                $table->boolean('show_on_homepage')->default(true)->after('is_featured');
            }
        });

        Schema::table('dealers', function (Blueprint $table) {
            if (!Schema::hasColumn('dealers', 'default_marketplace_margin')) {
                $table->decimal('default_marketplace_margin', 8, 2)->default(20)->after('balance'); // bayi karı %
            }
            if (!Schema::hasColumn('dealers', 'integration_api_key')) {
                $table->string('integration_api_key', 64)->nullable()->after('xml_token');
            }
            if (!Schema::hasColumn('dealers', 'auto_sync_enabled')) {
                $table->boolean('auto_sync_enabled')->default(true)->after('integration_api_key');
            }
            if (!Schema::hasColumn('dealers', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('auto_sync_enabled');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'cargo_pdf_path')) {
                $table->string('cargo_pdf_path')->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('orders', 'cargo_label_uploaded_at')) {
                $table->timestamp('cargo_label_uploaded_at')->nullable()->after('cargo_pdf_path');
            }
            if (!Schema::hasColumn('orders', 'payment_proof_path')) {
                $table->string('payment_proof_path')->nullable()->after('cargo_label_uploaded_at');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
