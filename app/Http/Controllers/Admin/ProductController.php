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
        $query = Product::with('source')->latest();

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

        $product->fill($data);

        if (isset($data['cost_price']) || isset($data['xml_margin_percent'])) {
            $pricing->applyToProduct($product, $data['xml_margin_percent'] ?? null);
        } else {
            $product->save();
        }

        return redirect()->route('admin.products.index')->with('success', 'Ürün güncellendi.');
    }

    /** Tüm aktif kaynaklardan ürünleri yeniden çek */
    public function pullAll(XmlImportService $importService)
    {
        $sources = Source::where('is_active', true)->get();
        $totalCreated = 0;
        $totalUpdated = 0;
        $errors = [];

        foreach ($sources as $source) {
            try {
                if ($source->type === 'url' && $source->url) {
                    $import = $importService->importFromUrl($source, auth()->id());
                } elseif ($source->file_path) {
                    $path = \Storage::disk('local')->path($source->file_path);
                    if (!file_exists($path)) {
                        $path = storage_path('app/'.$source->file_path);
                    }
                    if (file_exists($path)) {
                        $import = $importService->importFromFile($source, $path, auth()->id());
                    } else {
                        $errors[] = $source->name.': dosya bulunamadı';
                        continue;
                    }
                } else {
                    continue;
                }
                $totalCreated += $import->created_count ?? 0;
                $totalUpdated += $import->updated_count ?? 0;
            } catch (\Throwable $e) {
                $errors[] = $source->name.': '.$e->getMessage();
            }
        }

        // Fiyatları yeniden uygula
        app(PricingService::class)->bulkApplyXmlMargin(
            app(PricingService::class)->defaultXmlMargin()
        );

        \Cache::flush();

        $msg = "Çekim tamam: {$totalCreated} yeni, {$totalUpdated} güncellendi.";
        if ($errors) {
            $msg .= ' Hatalar: '.implode('; ', $errors);
            return back()->with('error', $msg);
        }

        return back()->with('success', $msg);
    }

    /** Toplu kar oranı uygula */
    public function bulkMargin(Request $request, PricingService $pricing)
    {
        $data = $request->validate([
            'xml_margin_percent' => 'required|numeric|min:0|max:500',
        ]);

        $count = $pricing->bulkApplyXmlMargin((float) $data['xml_margin_percent']);
        \Cache::flush();

        return back()->with('success', "{$count} ürüne %{$data['xml_margin_percent']} XML kar oranı uygulandı.");
    }
}
