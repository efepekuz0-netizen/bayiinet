<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DealerTrendyolListing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Source;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\XmlImport;
use App\Services\XmlImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SourceController extends Controller
{
    public function index()
    {
        $sources = Source::withCount('products')->latest()->get();
        $imports = XmlImport::with('source')->latest()->take(20)->get();

        return view('admin.sources.index', compact('sources', 'imports'));
    }

    public function create()
    {
        return view('admin.sources.create');
    }

    public function edit(Source $source)
    {
        return view('admin.sources.edit', compact('source'));
    }

    public function update(Request $request, Source $source)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'nullable|url:http,https',
            'priority' => 'required|integer|min:1|max:100',
            'xml_margin_percent' => 'nullable|numeric|min:0|max:500',
            'min_margin_percent' => 'nullable|numeric|min:0|max:500',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'prices_include_tax' => 'nullable|boolean',
        ]);
        $data['prices_include_tax'] = $request->boolean('prices_include_tax');
        $source->update($data);

        if ($request->boolean('recalculate')) {
            \App\Jobs\ApplyBulkXmlMargin::dispatch(
                (float) ($source->xml_margin_percent ?? app(\App\Services\PricingService::class)->defaultXmlMargin()),
                true,
                $source->id,
                auth()->id()
            );
        }

        \Illuminate\Support\Facades\Cache::forget('xml_feed_catalog');
        $msg = 'Kaynak ayarları güncellendi.';
        if ($request->boolean('recalculate')) {
            $msg .= ' Ürün fiyatları arka planda yeniden hesaplanıyor.';
        }
        return redirect()->route('admin.sources.index')->with('success', $msg);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:file,url',
            'url' => 'exclude_unless:type,url|required|url:http,https',
            'priority' => 'nullable|integer|min:1|max:100',
            'xml_margin_percent' => 'nullable|numeric|min:0|max:500',
            'min_margin_percent' => 'nullable|numeric|min:0|max:500',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'prices_include_tax' => 'nullable|boolean',
        ]);

        $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);
        $data['is_active'] = true;
        $data['priority'] = $data['priority'] ?? 10;
        $data['prices_include_tax'] = $request->boolean('prices_include_tax');

        Source::create($data);

        return redirect()->route('admin.sources.index')->with('success', 'Kaynak oluşturuldu.');
    }

    public function upload(Request $request, Source $source, XmlImportService $importService)
    {
        $request->validate([
            'xml_file' => 'required|file|mimes:xml,txt|max:51200', // 50MB
        ]);

        $file = $request->file('xml_file');
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs("xml/{$source->id}", Str::uuid().".{$extension}");

        if ($path === false) {
            throw new \RuntimeException('XML dosyası güvenli depolama alanına kaydedilemedi.');
        }

        $source->update(['file_path' => $path, 'last_error' => null]);

        // Büyük XML'ler (binlerce ürün) HTTP isteğini zaman aşımına düşürdüğü
        // için içe aktarma arka planda çalışır.
        \App\Jobs\ImportSourceJob::dispatch($source->id, auth()->id(), false);

        return redirect()->route('admin.sources.index')
            ->with('success', 'XML yüklendi, içe aktarma arka planda başlatıldı. Sonuçlar «İçe Aktarma Geçmişi» bölümüne yazılacak.');
    }

    public function refresh(Source $source)
    {
        abort_unless($source->type === 'url', 404);

        // Büyük kaynaklarda istek zaman aşımına düşmemek için arka planda çalışır.
        \App\Jobs\ImportSourceJob::dispatch($source->id, auth()->id(), false);

        return redirect()->route('admin.sources.index')
            ->with('success', "«{$source->name}» kaynağı için XML yenileme arka planda başlatıldı. Sonuç «İçe Aktarma Geçmişi» bölümüne yazılacak.");
    }

    public function destroy(Source $source)
    {
        $name = $source->name;
        $deletedProducts = 0;

        DB::transaction(function () use ($source, &$deletedProducts): void {
            $productIds = Product::query()->where('source_id', $source->id)->pluck('id');
            $deletedProducts = $productIds->count();

            if ($deletedProducts > 0) {
                ProductVariant::query()->whereIn('product_id', $productIds)->delete();
                // Listing cascade zaten product FK ile silinir; yine de temizlik
                DealerTrendyolListing::query()->whereIn('product_id', $productIds)->delete();
                Product::query()->whereIn('id', $productIds)->delete();
            }

            $source->delete();
        });

        Cache::forget('xml_feed_catalog');

        return redirect()->route('admin.sources.index')->with(
            'success',
            "Kaynak «{$name}» silindi. {$deletedProducts} ürün (ve varyantları) kaldırıldı."
        );
    }
}
