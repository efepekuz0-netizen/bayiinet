<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('variants')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('stock', '>', 0)
                    ->orWhereHas('variants', fn ($variants) => $variants->where('stock', '>', 0));
            })
            ->latest('last_synced_at');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('stock_code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate(24)->withQueryString();
        $dealer = auth()->user()->dealer;
        $profitMargin = (float) PlatformSetting::read('default_profit_margin', '0');

        return view('dealer.products.index', compact('products', 'dealer', 'profitMargin'));
    }
}
