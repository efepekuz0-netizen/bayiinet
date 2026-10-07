<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\XmlFeedController;
use App\Models\BalanceTransaction;
use App\Models\BlacklistEntry;
use App\Models\Category;
use App\Models\DealerAnnouncement;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\XmlImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OperationsController extends Controller
{
    public function customers(Request $request)
    {
        $query = Order::query();
        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }
        $customers = $query
            ->selectRaw('customer_name, customer_phone, customer_email, customer_city, count(*) as order_count, sum(total) as total_spent, max(created_at) as last_order_at')
            ->groupBy('customer_name', 'customer_phone', 'customer_email', 'customer_city')
            ->latest('last_order_at')
            ->paginate(30)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function criticalStock()
    {
        $threshold = (int) PlatformSetting::read('critical_stock_threshold', '5');
        $products = Product::with('variants', 'source')
            ->where('is_active', true)
            ->where(function ($query) use ($threshold) {
                $query->where(function ($plain) use ($threshold) {
                    $plain->where('has_variants', false)->where('stock', '<=', $threshold);
                })->orWhere(function ($variant) use ($threshold) {
                    $variant->where('has_variants', true)
                        ->whereHas('variants', fn ($stock) => $stock->where('stock', '<=', $threshold));
                });
            })
            ->orderBy('stock')
            ->paginate(40);

        return view('admin.products.critical-stock', compact('products', 'threshold'));
    }

    public function blacklist()
    {
        $entries = BlacklistEntry::latest()->paginate(30);

        return view('admin.blacklist.index', compact('entries'));
    }

    public function storeBlacklist(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['product', 'customer'])],
            'value' => 'required|string|max:255',
            'reason' => 'nullable|string|max:255',
        ]);
        $data['value'] = trim($data['value']);

        BlacklistEntry::query()->updateOrCreate(
            ['type' => $data['type'], 'value' => $data['value']],
            ['reason' => $data['reason'] ?? null, 'created_by' => auth()->id()]
        );

        return back()->with('success', 'Kara liste kaydı güncellendi.');
    }

    public function destroyBlacklist(BlacklistEntry $entry)
    {
        $entry->delete();

        return back()->with('success', 'Kara liste kaydı silindi.');
    }

    public function taxonomy()
    {
        $categories = Category::withCount('products')->orderBy('name')->paginate(30);
        $brands = Product::query()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->selectRaw('brand, count(*) as products_count')
            ->groupBy('brand')
            ->orderBy('brand')
            ->get();

        return view('admin.catalog.taxonomy', compact('categories', 'brands'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'full_path' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:categories,id',
        ]);
        $data['full_path'] = $data['full_path'] ?? $data['name'];
        $data['slug'] = Str::slug($data['full_path']).'-'.Str::random(5);
        $data['is_active'] = true;

        Category::create($data);

        return back()->with('success', 'Kategori oluşturuldu.');
    }

    public function updateBrand(Request $request)
    {
        $data = $request->validate([
            'current_brand' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
        ]);

        Product::query()->where('brand', $data['current_brand'])->update(['brand' => $data['brand']]);
        Cache::forget('xml_feed_catalog');

        return back()->with('success', 'Marka adı ürün kataloğunda güncellendi.');
    }

    public function destroyCategory(Category $category)
    {
        $category->delete();

        return back()->with('success', 'Kategori silindi.');
    }

    public function pricing()
    {
        $settings = [
            'xml_margin_percent' => PlatformSetting::read('xml_margin_percent', '15'),
            'min_margin_percent' => PlatformSetting::read('min_margin_percent', '5'),
            'default_marketplace_margin' => PlatformSetting::read('default_marketplace_margin', '20'),
            'xml_tax_rate' => PlatformSetting::read('xml_tax_rate', '20'),
            'xml_prices_include_tax' => PlatformSetting::read('xml_prices_include_tax', '0'),
        ];
        $products = Product::query()->where('is_active', true)->latest()->paginate(30);
        $lastBulk = Cache::get('pricing_bulk_last_result');

        return view('admin.pricing.index', compact('settings', 'products', 'lastBulk'));
    }

    public function updatePricing(Request $request)
    {
        $data = $request->validate([
            'xml_margin_percent' => 'required|numeric|min:0|max:500',
            'min_margin_percent' => 'required|numeric|min:0|max:500',
            'default_marketplace_margin' => 'required|numeric|min:0|max:500',
            'xml_tax_rate' => 'required|numeric|min:0|max:100',
            'xml_prices_include_tax' => 'nullable|boolean',
            'apply_to_all' => 'nullable|boolean',
        ]);

        PlatformSetting::write('xml_margin_percent', $data['xml_margin_percent']);
        PlatformSetting::write('min_margin_percent', $data['min_margin_percent']);
        PlatformSetting::write('default_marketplace_margin', $data['default_marketplace_margin']);
        PlatformSetting::write('xml_tax_rate', $data['xml_tax_rate']);
        PlatformSetting::write('xml_prices_include_tax', $request->boolean('xml_prices_include_tax') ? '1' : '0');

        $msg = 'Kar oranları kaydedildi.';

        if ($request->boolean('apply_to_all')) {
            // Arka planda uygula — sayfa anında yanıt verir, uzun bekleme olmaz
            \App\Jobs\ApplyBulkXmlMargin::dispatch(
                (float) $data['xml_margin_percent'],
                true,
                null,
                auth()->id()
            );
            $msg .= ' Tüm ürünlere uygulama arka planda başlatıldı. Birkaç saniye / dakika içinde tamamlanır.';
        }

        Cache::forget('xml_feed_catalog');

        return back()->with('success', $msg);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'critical_stock_threshold' => 'required|integer|min:0|max:100000',
            'xml_margin_percent' => 'nullable|numeric|min:0|max:500',
            'min_margin_percent' => 'nullable|numeric|min:0|max:500',
            'default_marketplace_margin' => 'nullable|numeric|min:0|max:500',
            'xml_tax_rate' => 'nullable|numeric|min:0|max:100',
            'xml_prices_include_tax' => 'nullable|boolean',
            'company_name' => 'nullable|string|max:255',
        ]);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                PlatformSetting::write($key, $value);
            }
        }

        PlatformSetting::write('xml_prices_include_tax', $request->boolean('xml_prices_include_tax') ? '1' : '0');
        if (array_key_exists('xml_margin_percent', $data) && $data['xml_margin_percent'] !== null) {
            \App\Jobs\ApplyBulkXmlMargin::dispatch(
                (float) $data['xml_margin_percent'],
                array_key_exists('xml_tax_rate', $data),
                null,
                auth()->id()
            );
        }
        Cache::forget('xml_feed_catalog');

        return back()->with('success', 'Panel ayarları kaydedildi. Kar oranı değiştiyse ürün fiyatları arka planda güncelleniyor.');
    }

    public function settings()
    {
        $settings = [
            'critical_stock_threshold' => PlatformSetting::read('critical_stock_threshold', '5'),
            'xml_margin_percent' => PlatformSetting::read('xml_margin_percent', '15'),
            'min_margin_percent' => PlatformSetting::read('min_margin_percent', '5'),
            'default_marketplace_margin' => PlatformSetting::read('default_marketplace_margin', '20'),
            'company_name' => PlatformSetting::read('company_name', 'BayiXML'),
            'xml_tax_rate' => PlatformSetting::read('xml_tax_rate', '20'),
            'xml_prices_include_tax' => PlatformSetting::read('xml_prices_include_tax', '0'),
        ];

        return view('admin.settings.index', compact('settings'));
    }

    public function exportXml(XmlFeedController $feed)
    {
        return response($feed->publicFeed()->getContent(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="bayixml-urunler.xml"',
        ]);
    }

    public function imports()
    {
        $imports = XmlImport::with(['source', 'user'])->latest()->paginate(40);
        $transactions = BalanceTransaction::with(['dealer', 'creator'])->latest()->take(30)->get();

        return view('admin.logs.index', compact('imports', 'transactions'));
    }

    public function announcements()
    {
        $announcements = DealerAnnouncement::latest()->paginate(20);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function storeAnnouncement(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = auth()->id();

        DealerAnnouncement::create($data);

        return back()->with('success', 'Bayi duyurusu yayınlandı.');
    }

    public function destroyAnnouncement(DealerAnnouncement $announcement)
    {
        $announcement->delete();

        return back()->with('success', 'Duyuru silindi.');
    }
}
