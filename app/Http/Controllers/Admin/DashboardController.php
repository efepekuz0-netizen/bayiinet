<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Source;
use App\Models\XmlImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $margin = app(\App\Services\PricingService::class)->profitMargin();
        $threshold = (int) PlatformSetting::read('critical_stock_threshold', '5');

        $from = now()->subDays(13)->startOfDay();

        $dailyRaw = Order::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $dailyOrders = collect(range(13, 0))->map(function (int $daysAgo) use ($dailyRaw) {
            $date = now()->subDays($daysAgo)->startOfDay();
            $key = $date->toDateString();

            return [
                'label' => $date->format('d.m'),
                'count' => (int) ($dailyRaw[$key] ?? 0),
            ];
        });

        $orderAgg = Order::query()
            ->where('created_at', '>=', $from)
            ->selectRaw("COUNT(*) as total_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','returned') THEN total ELSE 0 END),0) as revenue")
            ->first();

        $stats = Cache::remember('admin_dash_stats_v2', 60, function () use ($threshold, $orderAgg, $margin) {
            $critical = Product::query()
                ->where('is_active', true)
                ->where(function ($query) use ($threshold) {
                    $query->where(fn ($plain) => $plain->where('has_variants', false)->where('stock', '<=', $threshold))
                        ->orWhere(fn ($variant) => $variant->where('has_variants', true)
                            ->whereHas('variants', fn ($stock) => $stock->where('stock', '<=', $threshold)));
                })->count();

            $stockUnits = (int) DB::table('products')->where('is_active', true)->where('has_variants', false)->sum('stock')
                + (int) DB::table('product_variants')
                    ->join('products', 'products.id', '=', 'product_variants.product_id')
                    ->where('products.is_active', true)
                    ->sum('product_variants.stock');

            return [
                'orders' => (int) ($orderAgg->total_count ?? 0),
                'revenue' => (float) ($orderAgg->revenue ?? 0),
                'estimated_margin' => (float) ($orderAgg->revenue ?? 0) * $margin / 100,
                'products' => Product::count(),
                'active_products' => Product::where('is_active', true)->count(),
                'critical_stock' => $critical,
                'dealers' => Dealer::count(),
                'active_dealers' => Dealer::where('status', 'active')->count(),
                'pending_dealers' => Dealer::where('status', 'pending')->count(),
                'sources' => Source::count(),
                'stock_units' => $stockUnits,
                'critical_threshold' => $threshold,
                'margin_rate' => $margin,
            ];
        });

        // keep threshold/margin fresh
        $stats['critical_threshold'] = $threshold;
        $stats['margin_rate'] = $margin;
        $stats['orders'] = (int) ($orderAgg->total_count ?? 0);
        $stats['revenue'] = (float) ($orderAgg->revenue ?? 0);
        $stats['estimated_margin'] = $stats['revenue'] * $margin / 100;

        $statusCounts = Order::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentOrders = Order::with('dealer:id,company_name')->latest()->take(8)->get();
        $recentImports = XmlImport::with('source:id,name')->latest()->take(7)->get();
        $dealers = Dealer::with('user:id,name,email')->withCount('orders')->latest()->take(5)->get();
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
