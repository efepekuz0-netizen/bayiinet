<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Source;
use App\Services\PricingService;
use App\Services\XmlImportService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->with('source:id,name')->select(['id','source_id','title','stock_code','barcode','brand','price','sell_price','stock','is_active','has_variants','updated_at'])->latest();

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('stock_code', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $products = $query->paginate(30)->withQueryString();
        $sources = Source::where('is_active', true)->get();

        return view('admin.products.index', compact('products', 'sources'));
    }

    public function edit(Product $product)
    {
        $product->load('variants');
        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product, PricingService $pricing)
    {
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'cost_price' => 'nullable|numeric|min:0',
            'xml_margin_percent' => 'nullable|numeric|min:0|max:500',
            'min_margin_percent' => 'nullable|numeric|min:0|max:500',
            'stock' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'show_on_homepage' => 'boolean',
            'brand' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['show_on_homepage'] = $request->boolean('show_on_homepage');

        $original = $product->only(['cost_price', 'xml_margin_percent', 'min_margin_percent', 'stock', 'is_active']);
        $product->fill($data);

        if (isset($data['cost_price']) || isset($data['xml_margin_percent'])) {
            $pricing->applyToProduct($product, $data['xml_margin_percent'] ?? null);
        } else {
            $product->save();
        }

        \App\Services\AdminAudit::log('product.update', $product->title.' ürünü güncellendi.', [
            'product_id' => $product->id,
            'before' => $original,
            'after' => $product->only(['cost_price', 'xml_margin_percent', 'min_margin_percent', 'stock', 'is_active']),
        ]);

        return redirect()->route('admin.products.index')->with('success', 'Ürün güncellendi.');
    }

    /** Tüm aktif kaynaklardan ürünleri yeniden çek (arka planda) */
    public function pullAll()
    {
        $sources = Source::where('is_active', true)->get();
        $queued = 0;
        $errors = [];

        foreach ($sources as $source) {
            try {
                if (! (($source->type === 'url' && $source->url) || $source->file_path)) {
                    continue;
                }
                if ($source->type === 'file' && $source->file_path) {
                    $path = \Storage::disk('local')->path($source->file_path);
                    if (!file_exists($path) && !file_exists(storage_path('app/'.$source->file_path))) {
                        $errors[] = $source->name.': dosya bulunamadı';
                        continue;
                    }
                }
                // İçe aktarma kuyrukta çalışır: istek zaman aşımına düşmez.
                \App\Jobs\ImportSourceJob::dispatch($source->id, auth()->id(), false);
                $queued++;
            } catch (\Throwable $e) {
                $errors[] = $source->name.': '.$e->getMessage();
            }
        }

        // Not: fiyatlar içe aktarma sırasında, her kaynağın kendi kâr oranıyla
        // toplu olarak hesaplanıyor. Burada ayrıca genel bir kâr oranı
        // uygulanmıyor (kaynağa özel oranları eziyordu).

        \Cache::forget('xml_feed_catalog');
        \Cache::forget('home_main_categories_v2');
        \Cache::forget('admin_dash_stats_v2');

        $msg = "{$queued} kaynak için XML çekimi arka planda başlatıldı. Ürünler ve fiyatlar tamamlandıkça güncellenecek — «İçe Aktarma Geçmişi» bölümünden takip edebilirsiniz.";
        if ($errors) {
            $msg .= ' Hatalar: '.implode('; ', $errors);
            return back()->with('error', $msg);
        }

        return back()->with('success', $msg);
    }

    /** Toplu kar oranı uygula — arka planda çalışır */
    public function bulkMargin(Request $request, PricingService $pricing)
    {
        $data = $request->validate([
            'xml_margin_percent' => 'required|numeric|min:0|max:500',
        ]);

        \App\Jobs\ApplyBulkXmlMargin::dispatch(
            (float) $data['xml_margin_percent'],
            false,
            null,
            auth()->id()
        );
        \Cache::forget('xml_feed_catalog');

        return back()->with('success', "%{$data['xml_margin_percent']} kar oranı tüm ürünlere arka planda uygulanıyor. Kısa süre içinde tamamlanır.");
    }
}
