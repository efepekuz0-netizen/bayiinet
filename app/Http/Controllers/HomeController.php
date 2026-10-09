<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Product::query()->where('is_active', true);

            // Kolon yoksa (migration kaçmışsa) siteyi düşürme
            if ($this->hasProductColumn('show_on_homepage')) {
                $query->where(function ($q) {
                    $q->where('show_on_homepage', true)->orWhereNull('show_on_homepage');
                });
            }

            // Stok: has_variants false + stock>0 VEYA varyantlı (basit, ağır orWhereHas yok)
            $query->where(function ($q) {
                $q->where(function ($plain) {
                    $plain->where('has_variants', false)->where('stock', '>', 0);
                })->orWhere('has_variants', true);
            });

            if ($search = trim((string) $request->get('q', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('stock_code', 'like', "%{$search}%");
                });
            }

            if ($cat = trim((string) $request->get('kategori', ''))) {
                $query->where(function ($q) use ($cat) {
                    $q->where('main_category', $cat)
                        ->orWhere('category_path', 'like', "%{$cat}%");
                });
            }

            $orderCol = $this->hasProductColumn('last_synced_at') ? 'last_synced_at' : 'id';
            $products = $query->orderByDesc($orderCol)->paginate(24)->withQueryString();

            $categories = Cache::remember('home_main_categories_v2', 600, function () {
                try {
                    return Product::query()
                        ->where('is_active', true)
                        ->whereNotNull('main_category')
                        ->where('main_category', '!=', '')
                        ->select('main_category')
                        ->distinct()
                        ->orderBy('main_category')
                        ->pluck('main_category')
                        ->values();
                } catch (Throwable $e) {
                    Log::warning('home categories failed', ['error' => $e->getMessage()]);

                    return collect();
                }
            });

            $featured = collect();
            if ($this->hasProductColumn('is_featured')) {
                try {
                    $featured = Product::query()
                        ->where('is_active', true)
                        ->where('is_featured', true)
                        ->where(function ($q) {
                            $q->where(function ($plain) {
                                $plain->where('has_variants', false)->where('stock', '>', 0);
                            })->orWhere('has_variants', true);
                        })
                        ->orderByDesc($orderCol)
                        ->take(8)
                        ->get();
                } catch (Throwable $e) {
                    Log::warning('home featured failed', ['error' => $e->getMessage()]);
                }
            }

            return view('home', compact('products', 'categories', 'featured'));
        } catch (Throwable $e) {
            Log::error('HomeController::index failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Boş katalog ile yine de sayfa açılsın
            $products = Product::query()->whereRaw('1=0')->paginate(24);
            $categories = collect();
            $featured = collect();

            return view('home', compact('products', 'categories', 'featured'))
                ->with('error', 'Katalog geçici olarak yüklenemedi. Lütfen biraz sonra tekrar deneyin.');
        }
    }

    public function product(Product $product)
    {
        abort_unless($product->is_active, 404);

        if ($this->hasProductColumn('show_on_homepage') && $product->show_on_homepage === false) {
            abort(404);
        }

        $product->load('variants');

        $related = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->when($product->main_category, fn ($q) => $q->where('main_category', $product->main_category))
            ->where(function ($q) {
                $q->where(function ($plain) {
                    $plain->where('has_variants', false)->where('stock', '>', 0);
                })->orWhere('has_variants', true);
            })
            ->take(8)
            ->get();

        return view('product-detail', compact('product', 'related'));
    }

    private function hasProductColumn(string $column): bool
    {
        try {
            return Cache::remember(
                'schema_products_has_'.$column,
                3600,
                fn () => Schema::hasColumn('products', $column)
            );
        } catch (Throwable) {
            return false;
        }
    }
}
