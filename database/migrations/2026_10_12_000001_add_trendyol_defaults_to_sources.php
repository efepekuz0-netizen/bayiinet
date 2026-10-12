<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            if (! Schema::hasColumn('sources', 'default_trendyol_brand_id')) {
                $table->unsignedBigInteger('default_trendyol_brand_id')->nullable()->after('prices_include_tax');
            }
            if (! Schema::hasColumn('sources', 'default_trendyol_category_id')) {
                $table->unsignedBigInteger('default_trendyol_category_id')->nullable()->after('default_trendyol_brand_id');
            }
            if (! Schema::hasColumn('sources', 'default_trendyol_brand_name')) {
                $table->string('default_trendyol_brand_name')->nullable()->after('default_trendyol_category_id');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
