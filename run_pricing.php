<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Örnek ürünlerin fiyat ve maliyet detayları:\n";
$products = App\Models\Product::take(5)->get();
foreach ($products as $p) {
    echo "Ürün: {$p->title} | Maliyet: {$p->cost_price} | Satış: {$p->price} | Marj: {$p->xml_margin_percent}%\n";
}

echo "\nPricingService ile toplu marj güncelleniyor...\n";
$source = App\Models\Source::find(2); // teknodayim
if ($source) {
    $pricingService = new \App\Services\PricingService();
    // Kaynağa bağlı ürünlerin fiyatlarını güncelleyelim
    $count = App\Models\Product::where(source_id, $source->id)->count();
    echo "Güncellenecek ürün sayısı: {$count}\n";
    
    // Toplu marj jobını
