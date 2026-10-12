<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Kaynak adı (ör: "Ana Tedarikçi XML")
            $table->string('slug')->unique();
            $table->enum('type', ['file', 'url'])->default('file');
            $table->string('url')->nullable();               // URL ile çekiliyorsa
            $table->string('file_path')->nullable();         // Yüklenen dosya yolu
            $table->json('mapping')->nullable();             // XML alan eşleştirmesi
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(10); // Aynı ürün birden fazla kaynaktan gelirse öncelik
            $table->timestamp('last_imported_at')->nullable();
            $table->unsignedInteger('last_product_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
