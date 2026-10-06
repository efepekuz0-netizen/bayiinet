<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Source;
use App\Models\XmlImport;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $margin = (float) PlatformSetting::read('default_profit_margin', '0');
        $threshold = (int) PlatformSetting::read('critical_stock_threshold', '5');
        $orders = Order::query()->where('created_at', '>=', now()->subDays(13)->startOfDay())->get(['total', 'status', 'created_at']);
        $dailyOrders = collect(range(13, 0))->map(function (int $daysAgo) use ($orders) {
            $date = now()->subDays($daysAgo)->startOfDay();
            $daily = $orders->filter(fn (Order $order) => $order->created_at->isSameDay($date));

            return [
                'label' => $date->format('d.m'),
                'count' => $daily->count(),
            ];
        });

        $criticalProducts = Product::query()
            ->where('is_active', true)
            ->where(function ($query) use ($threshold) {
                $query->where(fn ($plain) => $plain->where('has_variants', false)->where('stock', '<=', $threshold))
                    ->orWhere(fn ($variant) => $variant->where('has_variants', true)
                        ->whereHas('variants', fn ($stock) => $stock->where('stock', '<=', $threshold)));
            })->count();

        $stats = [
            'orders' => $orders->count(),
            'revenue' => $orders->whereNotIn('status', ['cancelled', 'returned'])->sum(fn (Order $order) => (float) $order->total),
            'estimated_margin' => $orders->whereNotIn('status', ['cancelled', 'returned'])->sum(fn (Order $order) => (float) $order->total) * $margin / 100,
            'products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'critical_stock' => $criticalProducts,
            'dealers' => Dealer::count(),
            'active_dealers' => Dealer::where('status', 'active')->count(),
            'pending_dealers' => Dealer::where('status', 'pending')->count(),
            'sources' => Source::count(),
            'stock_units' => DB::table('products')->where('is_active', true)->where('has_variants', false)->sum('stock')
                + DB::table('product_variants')->join('products', 'products.id', '=', 'product_variants.product_id')
                    ->where('products.is_active', true)->sum('product_variants.stock'),
            'critical_threshold' => $threshold,
            'margin_rate' => $margin,
        ];

        $statusCounts = Order::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $recentOrders = Order::with('dealer')->latest()->take(8)->get();
        $recentImports = XmlImport::with('source')->latest()->take(7)->get();
        $dealers = Dealer::with('user')->withCount('orders')->latest()->take(5)->get();
        $sources = Source::withCount('products')->latest()->take(6)->get();

        return view('admin.dashboard', compact(
            'stats',
            'statusCounts',
            'dailyOrders',
            'recentOrders',
            'recentImports',
            'dealers',
            'sources'
        ));
    }
}
