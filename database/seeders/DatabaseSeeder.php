<?php

namespace Database\Seeders;

use App\Models\Dealer;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@bayiinet.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $dealerUser = User::create([
            'name' => 'Test Bayi',
            'email' => 'bayi@bayiinet.test',
            'password' => Hash::make('password'),
            'role' => 'dealer',
        ]);

        $dealer = Dealer::create([
            'user_id' => $dealerUser->id,
            'company_name' => 'Test Bayi Ltd.',
            'tax_number' => '1234567890',
            'phone' => '05321234567',
            'city' => 'İstanbul',
            'district' => 'Kadıköy',
            'address' => 'Test Mah. Test Sok. No:1',
            'xml_token' => Str::random(48),
            'balance' => 5000.00,
            'status' => 'active',
            'approved_at' => now(),
        ]);

        $source = Source::create([
            'name' => 'Demo Kaynak',
            'slug' => 'demo-kaynak',
            'type' => 'file',
            'is_active' => true,
            'priority' => 10,
        ]);

        $products = [
            [
                'stock_code' => 'DEMO-001',
                'barcode' => '8690000000001',
                'title' => 'Demo Ürün 1 - Siyah Tişört',
                'brand' => 'DemoBrand',
                'description' => 'Yüksek kaliteli pamuklu tişört. Rahat kesim.',
                'main_category' => 'Giyim',
                'sub_category' => 'Tişört',
                'category_path' => 'Giyim >>> Tişört',
                'price' => 103.39, 'cost_price' => 89.90, 'xml_margin_percent' => 15, 'sell_price' => 103.39, 'show_on_homepage' => true, 'is_featured' => true,
                'tax_rate' => 10,
                'desi' => 0.5,
                'stock' => 150,
                'images' => ['https://via.placeholder.com/600x800?text=Tisort'],
            ],
            [
                'stock_code' => 'DEMO-002',
                'barcode' => '8690000000002',
                'title' => 'Demo Ürün 2 - Bluetooth Kulaklık',
                'brand' => 'SoundMax',
                'description' => 'Kablosuz bluetooth kulaklık, 20 saat pil ömrü.',
                'main_category' => 'Elektronik',
                'sub_category' => 'Kulaklık',
                'category_path' => 'Elektronik >>> Kulaklık',
                'price' => 286.35, 'cost_price' => 249.00, 'xml_margin_percent' => 15, 'sell_price' => 286.35, 'show_on_homepage' => true, 'is_featured' => true,
                'tax_rate' => 20,
                'desi' => 0.3,
                'stock' => 75,
                'images' => ['https://via.placeholder.com/600x600?text=Kulaklik'],
            ],
            [
                'stock_code' => 'DEMO-003',
                'barcode' => '8690000000003',
                'title' => 'Demo Ürün 3 - Mutfak Seti 5 Parça',
                'brand' => 'HomeCook',
                'description' => 'Dayanıklı paslanmaz çelik mutfak seti.',
                'main_category' => 'Ev & Yaşam',
                'sub_category' => 'Mutfak',
                'category_path' => 'Ev & Yaşam >>> Mutfak',
                'price' => 459.43, 'cost_price' => 399.50, 'xml_margin_percent' => 15, 'sell_price' => 459.43, 'show_on_homepage' => true,
                'tax_rate' => 10,
                'desi' => 2.0,
                'stock' => 40,
                'images' => ['https://via.placeholder.com/600x600?text=Mutfak'],
            ],
            [
                'stock_code' => 'DEMO-004',
                'barcode' => '8690000000004',
                'title' => 'Demo Ürün 4 - LED Masa Lambası',
                'brand' => 'LightHome',
                'description' => 'Dokunmatik LED masa lambası, 3 renk modu.',
                'main_category' => 'Ev & Yaşam',
                'sub_category' => 'Aydınlatma',
                'category_path' => 'Ev & Yaşam >>> Aydınlatma',
                'price' => 217.35, 'cost_price' => 189.00, 'xml_margin_percent' => 15, 'sell_price' => 217.35, 'show_on_homepage' => true,
                'tax_rate' => 10,
                'desi' => 1.0,
                'stock' => 3,
                'images' => ['https://via.placeholder.com/600x600?text=Lamba'],
            ],
        ];

        foreach ($products as $p) {
            Product::create(array_merge($p, [
                'source_id' => $source->id,
                'is_active' => true,
                'last_synced_at' => now(),
            ]));
        }

        PlatformSetting::write('critical_stock_threshold', '5');
        PlatformSetting::write('profit_margin', '25');
        PlatformSetting::write('company_name', 'Bayiinet');

        $this->command->info('Seed tamamlandi!');
        $this->command->info('Admin: admin@bayiinet.test / password');
        $this->command->info('Bayi:  bayi@bayiinet.test / password');
        $this->command->info('Bayi XML: /xml/'.$dealer->xml_token.'.xml');
    }
}
