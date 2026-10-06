<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table): void {
            $table->decimal('xml_margin_percent', 8, 2)->nullable()->after('mapping');
            $table->decimal('min_margin_percent', 8, 2)->nullable()->after('xml_margin_percent');
            $table->decimal('tax_rate', 8, 2)->nullable()->after('min_margin_percent');
            $table->boolean('prices_include_tax')->default(false)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table): void {
            $table->dropColumn(['xml_margin_percent', 'min_margin_percent', 'tax_rate', 'prices_include_tax']);
        });
    }
};
