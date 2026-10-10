<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            if (! Schema::hasColumn('dealers', 'hepsiburada_merchant_id')) {
                $table->string('hepsiburada_merchant_id')->nullable()->after('trendyol_last_error');
            }
            if (! Schema::hasColumn('dealers', 'hepsiburada_credentials')) {
                $table->longText('hepsiburada_credentials')->nullable()->after('hepsiburada_merchant_id');
            }
            if (! Schema::hasColumn('dealers', 'hepsiburada_last_error')) {
                $table->text('hepsiburada_last_error')->nullable()->after('hepsiburada_credentials');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
