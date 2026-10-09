<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceTransaction;
use App\Services\AdminAudit;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('dealer')->latest();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($q = trim((string) $request->get('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%")
                    ->orWhereHas('dealer', fn ($d) => $d->where('company_name', 'like', "%{$q}%"));
            });
        }

        $orders = $query->paginate(25)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['dealer', 'items.product']);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,paid,preparing,shipped,delivered,cancelled,returned',
            'cargo_company' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'admin_note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($order, $data) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $transitions = [
                'pending' => ['paid', 'preparing', 'cancelled'],
                'paid' => ['preparing', 'cancelled'],
                'preparing' => ['shipped', 'cancelled'],
                'shipped' => ['delivered', 'returned'],
                'delivered' => ['returned'],
                'cancelled' => [],
                'returned' => [],
            ];

            if (
                $lockedOrder->status !== $data['status']
                && ! in_array($data['status'], $transitions[$lockedOrder->status] ?? [], true)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Sipariş bu duruma geçirilemiyor.',
                ]);
            }

            if (in_array($data['status'], ['cancelled', 'returned'], true) && $lockedOrder->status !== $data['status']) {
                $lockedDealer = Dealer::query()->lockForUpdate()->findOrFail($lockedOrder->dealer_id);
                $newBalance = number_format(
                    ((int) round((float) $lockedDealer->balance * 100) + (int) round((float) $lockedOrder->total * 100)) / 100,
                    2,
                    '.',
                    ''
                );
                $lockedDealer->update(['balance' => $newBalance]);

                foreach ($lockedOrder->items as $item) {
                    if ($item->product_variant_id) {
                        ProductVariant::query()->whereKey($item->product_variant_id)->increment('stock', $item->quantity);
                    } else {
                        Product::query()->whereKey($item->product_id)->increment('stock', $item->quantity);
                    }
                }

                BalanceTransaction::create([
                    'dealer_id' => $lockedDealer->id,
                    'order_id' => $lockedOrder->id,
                    'type' => 'refund',
                    'amount' => $lockedOrder->total,
                    'balance_after' => $newBalance,
                    'description' => 'Sipariş iadesi: '.$lockedOrder->order_number,
                    'created_by' => auth()->id(),
                ]);
            }

            if ($data['status'] === 'shipped' && empty($lockedOrder->shipped_at)) {
                $data['shipped_at'] = now();
            }

            $lockedOrder->update($data);
        });
        AdminAudit::log('order.status', $order->order_number.' siparişi «'.$order->status.'» durumuna alındı.', [
            'order_id' => $order->id,
            'status' => $data['status'],
            'previous_status' => $order->getOriginal('status'),
        ]);

        Cache::forget('xml_feed_catalog');

        return back()->with('success', 'Sipariş güncellendi.');
    }
}
