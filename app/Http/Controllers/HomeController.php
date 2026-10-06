<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->where('is_active', true)
            ->where('show_on_homepage', true)
            ->where(function ($q) {
                $q->where('stock', '>', 0)
                  ->orWhereHas('variants', fn ($v) => $v->where('stock', '>', 0));
            });

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('stock_code', 'like', "%{$search}%");
            });
        }

        if ($cat = $request->get('kategori')) {
            $query->where(function ($q) use ($cat) {
                $q->where('main_category', $cat)
                  ->orWhere('category_path', 'like', "%{$cat}%");
            });
        }

        $products = $query->latest('last_synced_at')->paginate(24)->withQueryString();

        $categories = Product::query()
            ->where('is_active', true)
            ->whereNotNull('main_category')
            ->where('main_category', '!=', '')
            ->distinct()
            ->pluck('main_category')
            ->filter()
            ->sort()
            ->values();

        $featured = Product::query()
            ->where('is_active', true)
            ->where('is_featured', true)
            ->where('stock', '>', 0)
            ->take(8)
            ->get();

        return view('home', compact('products', 'categories', 'featured'));
    }

    public function product(Product $product)
    {
        abort_unless($product->is_active && $product->show_on_homepage, 404);
        $product->load('variants');

        $related = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->where('main_category', $product->main_category)
            ->take(4)
            ->get();

        return view('product-detail', compact('product', 'related'));
    }
}
