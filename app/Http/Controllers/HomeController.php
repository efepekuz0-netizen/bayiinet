<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $products = $this->emptyPaginator($request);
        $categories = collect();
        $featured = collect();
        $error = null;

        try {
            if (! Schema::hasTable('products')) {
                return view('home', compact('products', 'categories', 'featured'))
                    ->with('error', 'Ürün tablosu henüz hazır değil. Deploy/migrate bekleniyor.');
            }

            $query = Product::query()->where('is_active', true);

            // Sadece var olan kolonlarla filtrele — migration kaçmışsa patlamasın
            $cols = Schema::getColumnListing('products');

            if (in_array('show_on_homepage', $cols, true)) {
                $query->where(function ($q) {
                    $q->where('show_on_homepage', true)->orWhereNull('show_on_homepage');
                });
            }

            if (in_array('has_variants', $cols, true)) {
                $query->where(function ($q) {
                    $q->where(function ($plain) {
                        $plain->where('has_variants', false)->where('stock', '>', 0);
                    })->orWhere('has_variants', true);
                });
            } else {
                $query->where('stock', '>', 0);
            }

            if ($search = trim((string) $request->get('q', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('stock_code', 'like', "%{$search}%");
                });
            }

            $cat = trim((string) $request->get('kategori', ''));
            if ($cat !== '' && in_array('main_category', $cols, true)) {
                $query->where(function ($q) use ($cat, $cols) {
                    $q->where('main_category', $cat);
                    if (in_array('category_path', $cols, true)) {
                        $q->orWhere('category_path', 'like', "%{$cat}%");
                    }
                });
            }

            $orderCol = in_array('last_synced_at', $cols, true) ? 'last_synced_at' : 'id';
            $products = $query->orderByDesc($orderCol)->paginate(24)->withQueryString();

            if (in_array('main_category', $cols, true)) {
                $categories = Product::query()
                    ->where('is_active', true)
                    ->whereNotNull('main_category')
                    ->where('main_category', '!=', '')
                    ->distinct()
                    ->orderBy('main_category')
                    ->limit(40)
                    ->pluck('main_category');
            }

            if (in_array('is_featured', $cols, true)) {
                $featured = Product::query()
                    ->where('is_active', true)
                    ->where('is_featured', true)
                    ->orderByDesc($orderCol)
                    ->limit(8)
                    ->get();
            }
        } catch (Throwable $e) {
            Log::error('HomeController::index', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $error = 'Katalog geçici olarak yüklenemedi.';
            $products = $this->emptyPaginator($request);
            $categories = collect();
            $featured = collect();
        }

        try {
            return view('home', [
                'products' => $products,
                'categories' => $categories,
                'featured' => $featured,
                'error' => $error,
            ]);
        } catch (Throwable $e) {
            Log::error('HomeController::view', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Blade bile patlarsa düz HTML dön — 500 yerine mesaj
            return response(
                '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bayiinet</title></head><body style="font-family:system-ui;max-width:640px;margin:3rem auto;padding:0 1rem"><h1>Bayiinet</h1><p>Ana sayfa geçici olarak gösterilemiyor.</p><p><a href="/giris">Giriş yap</a> · <a href="/bayilik-basvurusu">Bayilik başvurusu</a></p><pre style="background:#f5f5f5;padding:1rem;font-size:12px;overflow:auto">'.e($e->getMessage()).'</pre></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }
    }

    public function product(Product $product)
    {
        abort_unless($product->is_active, 404);

        try {
            $product->load('variants');
        } catch (Throwable) {
            // varyant tablosu yoksa devam
        }

        $related = collect();
        try {
            $related = Product::query()
                ->where('is_active', true)
                ->where('id', '!=', $product->id)
                ->when($product->main_category, fn ($q) => $q->where('main_category', $product->main_category))
                ->limit(8)
                ->get();
        } catch (Throwable) {
        }

        return view('product-detail', compact('product', 'related'));
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 24, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
