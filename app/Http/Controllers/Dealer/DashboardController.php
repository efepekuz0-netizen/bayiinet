<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\DealerAnnouncement;
use App\Models\Order;
use App\Models\Product;

class DashboardController extends Controller
{
    public function account()
    {
        $dealer = auth()->user()->dealer;
        $recentOrders = Order::where('dealer_id', $dealer->id)->latest()->take(10)->get();

        return view('dealer.account', compact('dealer', 'recentOrders'));
    }

    public function index()
    {
        $dealer = auth()->user()->dealer;

        $stats = [
            'balance' => $dealer->balance,
            'orders' => Order::where('dealer_id', $dealer->id)->count(),
            'pending_orders' => Order::where('dealer_id', $dealer->id)->whereIn('status', ['pending', 'paid', 'preparing'])->count(),
            'products' => Product::where('is_active', true)->count(),
        ];

        $recentOrders = Order::where('dealer_id', $dealer->id)->latest()->take(8)->get();
        $announcements = DealerAnnouncement::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->latest()
            ->take(5)
            ->get();

        return view('dealer.dashboard', compact('dealer', 'stats', 'recentOrders', 'announcements'));
    }
}
