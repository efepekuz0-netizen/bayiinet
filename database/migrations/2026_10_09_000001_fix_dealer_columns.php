<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onay / askıya alma ve panel alanları için eksik kolonları tamamlar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            if (! Schema::hasColumn('dealers', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('dealers', 'integration_api_key')) {
                $table->string('integration_api_key', 64)->nullable()->after('xml_token');
            }
            if (! Schema::hasColumn('dealers', 'auto_sync_enabled')) {
                $table->boolean('auto_sync_enabled')->default(true)->after('integration_api_key');
            }
            if (! Schema::hasColumn('dealers', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('auto_sync_enabled');
            }
            if (! Schema::hasColumn('dealers', 'default_marketplace_margin')) {
                $table->decimal('default_marketplace_margin', 8, 2)->default(20)->after('balance');
            }
            if (! Schema::hasColumn('dealers', 'tax_document_path')) {
                $table->string('tax_document_path')->nullable()->after('tax_office');
            }
            if (! Schema::hasColumn('dealers', 'trendyol_seller_id')) {
                $table->string('trendyol_seller_id')->nullable();
            }
            if (! Schema::hasColumn('dealers', 'trendyol_credentials')) {
                $table->longText('trendyol_credentials')->nullable();
            }
            if (! Schema::hasColumn('dealers', 'trendyol_last_error')) {
                $table->text('trendyol_last_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        // kolonları geri alma riskli — production verisi korunur
    }
};
