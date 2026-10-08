<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            if (! Schema::hasColumn('dealers', 'tax_document_path')) {
                $table->string('tax_document_path')->nullable()->after('tax_office');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            if (Schema::hasColumn('dealers', 'tax_document_path')) {
                $table->dropColumn('tax_document_path');
            }
        });
    }
};
