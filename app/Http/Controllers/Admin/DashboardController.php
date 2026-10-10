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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardController extends Controller
{
    public function index()
    {
        $margin = 0.0;
        $threshold = 5;

        try {
            $margin = (float) app(\App\Services\PricingService::class)->profitMargin();
        } catch (Throwable $e) {
            Log::warning('dashboard margin', ['e' => $e->getMessage()]);
        }

        try {
            $threshold = (int) PlatformSetting::read('critical_stock_threshold', '5');
        } catch (Throwable $e) {
            Log::warning('dashboard threshold', ['e' => $e->getMessage()]);
        }

        $from = now()->subDays(13)->startOfDay();
        $dailyOrders = collect(range(13, 0))->map(fn (int $i) => [
            'label' => now()->subDays($i)->format('d.m'),
            'count' => 0,
        ]);
        $orderAgg = (object) ['total_count' => 0, 'revenue' => 0];

        try {
            $driver = DB::connection()->getDriverName();
            $dateSelect = $driver === 'pgsql' ? 'DATE(created_at)::text as d' : 'DATE(created_at) as d';
            $dateGroup = $driver === 'pgsql' ? 'DATE(created_at)::text' : 'DATE(created_at)';

            $dailyRaw = Order::query()
                ->where('created_at', '>=', $from)
                ->selectRaw($dateSelect.', COUNT(*) as c')
                ->groupBy(DB::raw($dateGroup))
                ->pluck('c', 'd');

            $dailyOrders = collect(range(13, 0))->map(function (int $daysAgo) use ($dailyRaw) {
                $date = now()->subDays($daysAgo)->startOfDay();

                return [
                    'label' => $date->format('d.m'),
                    'count' => (int) ($dailyRaw[$date->toDateString()] ?? 0),
                ];
            });
        } catch (Throwable $e) {
            Log::warning('dashboard daily', ['e' => $e->getMessage()]);
        }

        try {
            $orderAgg = Order::query()
                ->where('created_at', '>=', $from)
                ->selectRaw('COUNT(*) as total_count')
                ->selectRaw("COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','returned') THEN total ELSE 0 END),0) as revenue")
                ->first() ?? $orderAgg;
        } catch (Throwable $e) {
            Log::warning('dashboard orderAgg', ['e' => $e->getMessage()]);
        }

        $emptyStats = [
            'orders' => 0,
            'revenue' => 0.0,
            'estimated_margin' => 0.0,
            'products' => 0,
            'active_products' => 0,
            'critical_stock' => 0,
            'dealers' => 0,
            'active_dealers' => 0,
            'pending_dealers' => 0,
            'sources' => 0,
            'stock_units' => 0,
            'critical_threshold' => $threshold,
            'margin_rate' => $margin,
        ];

        try {
            $stats = Cache::remember('admin_dash_stats_v3', 60, function () use ($threshold, $orderAgg, $margin, $emptyStats) {
                $critical = 0;
                $stockUnits = 0;

                try {
                    $critical = Product::query()
                        ->where('is_active', true)
                        ->where(function ($query) use ($threshold) {
                            $query->where(fn ($plain) => $plain->where('has_variants', false)->where('stock', '<=', $threshold))
                                ->orWhere(fn ($variant) => $variant->where('has_variants', true)
                                    ->whereHas('variants', fn ($stock) => $stock->where('stock', '<=', $threshold)));
                        })->count();
                } catch (Throwable $e) {
                    Log::warning('dashboard critical', ['e' => $e->getMessage()]);
                }

                try {
                    $stockUnits = (int) DB::table('products')->where('is_active', true)->where('has_variants', false)->sum('stock');
                    if (Schema::hasTable('product_variants')) {
                        $stockUnits += (int) DB::table('product_variants')
                            ->join('products', 'products.id', '=', 'product_variants.product_id')
                            ->where('products.is_active', true)
                            ->sum('product_variants.stock');
                    }
                } catch (Throwable $e) {
                    Log::warning('dashboard stock', ['e' => $e->getMessage()]);
                }

                return [
                    'orders' => (int) ($orderAgg->total_count ?? 0),
                    'revenue' => (float) ($orderAgg->revenue ?? 0),
                    'estimated_margin' => (float) ($orderAgg->revenue ?? 0) * $margin / 100,
                    'products' => (int) Product::count(),
                    'active_products' => (int) Product::where('is_active', true)->count(),
                    'critical_stock' => $critical,
                    'dealers' => (int) Dealer::count(),
                    'active_dealers' => (int) Dealer::where('status', 'active')->count(),
                    'pending_dealers' => (int) Dealer::where('status', 'pending')->count(),
                    'sources' => (int) Source::count(),
                    'stock_units' => $stockUnits,
                    'critical_threshold' => $threshold,
                    'margin_rate' => $margin,
                ];
            });
        } catch (Throwable $e) {
            Log::warning('dashboard stats', ['e' => $e->getMessage()]);
            $stats = $emptyStats;
        }

        $stats['critical_threshold'] = $threshold;
        $stats['margin_rate'] = $margin;
        $stats['orders'] = (int) ($orderAgg->total_count ?? 0);
        $stats['revenue'] = (float) ($orderAgg->revenue ?? 0);
        $stats['estimated_margin'] = $stats['revenue'] * $margin / 100;

        $statusCounts = collect();
        $recentOrders = collect();
        $recentImports = collect();
        $dealers = collect();
        $sources = collect();

        try {
            $statusCounts = Order::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
        } catch (Throwable $e) {
            Log::warning('dashboard statusCounts', ['e' => $e->getMessage()]);
        }

        try {
            $recentOrders = Order::with('dealer:id,company_name')->latest()->take(8)->get();
        } catch (Throwable $e) {
            Log::warning('dashboard recentOrders', ['e' => $e->getMessage()]);
        }

        try {
            if (Schema::hasTable('xml_imports')) {
                $recentImports = XmlImport::with('source:id,name')->latest()->take(7)->get();
            }
        } catch (Throwable $e) {
            Log::warning('dashboard imports', ['e' => $e->getMessage()]);
        }

        try {
            $dealers = Dealer::with('user:id,name,email')->withCount('orders')->latest()->take(5)->get();
        } catch (Throwable $e) {
            Log::warning('dashboard dealers', ['e' => $e->getMessage()]);
        }

        try {
            $sources = Source::withCount('products')->latest()->take(6)->get();
        } catch (Throwable $e) {
            Log::warning('dashboard sources', ['e' => $e->getMessage()]);
        }

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
