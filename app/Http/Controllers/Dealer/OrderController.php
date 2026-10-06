<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\BalanceTransaction;
use App\Models\BlacklistEntry;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index()
    {
        $dealer = auth()->user()->dealer;
        $orders = Order::where('dealer_id', $dealer->id)->latest()->paginate(20);

        return view('dealer.orders.index', compact('orders'));
    }

    public function create(Request $request)
    {
        $products = Product::with('variants')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where(function ($products) {
                    $products->where('has_variants', false)->where('stock', '>', 0);
                })->orWhere(function ($products) {
                    $products->where('has_variants', true)
                        ->whereHas('variants', fn ($variants) => $variants->where('stock', '>', 0));
                });
            })
            ->orderBy('title')
            ->get();

        $selectedProduct = null;
        $selectedVariant = null;
        if ($request->filled('product')) {
            $selectedProduct = $products->firstWhere('id', (int) $request->integer('product'));
            if ($selectedProduct && $request->filled('variant')) {
                $selectedVariant = $selectedProduct->variants->firstWhere('id', (int) $request->integer('variant'));
            }
        }

        return view('dealer.orders.create', compact('products', 'selectedProduct', 'selectedVariant'));
    }

    public function store(Request $request)
    {
        $dealer = auth()->user()->dealer;

        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_city' => 'required|string|max:100',
            'customer_district' => 'nullable|string|max:100',
            'customer_address' => 'required|string',
            'customer_email' => 'nullable|email',
            'dealer_note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $order = DB::transaction(function () use ($dealer, $data) {
            $lockedDealer = Dealer::query()->lockForUpdate()->findOrFail($dealer->id);
            $customerValues = array_values(array_filter([
                $data['customer_email'] ?? null,
                $data['customer_phone'] ?? null,
            ]));

            if ($customerValues && BlacklistEntry::query()
                ->where('type', 'customer')
                ->whereIn('value', $customerValues)
                ->exists()) {
                throw ValidationException::withMessages([
                    'customer' => 'Bu müşteri bilgileri kara listede olduğu için sipariş açılamıyor.',
                ]);
            }

            $subtotalCents = 0;
            $orderItems = [];

            foreach ($data['items'] as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item['product_id']);

                if (! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => "{$product->title} artık sipariş edilemiyor.",
                    ]);
                }

                $productCodes = array_values(array_filter([$product->stock_code, $product->barcode]));
                $variant = null;
                if ($product->has_variants) {
                    if (empty($item['product_variant_id'])) {
                        throw ValidationException::withMessages([
                            'items' => "{$product->title} için bir varyant seçin.",
                        ]);
                    }

                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->find($item['product_variant_id']);

                    if (! $variant || $variant->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "{$product->title} seçilen varyant için yeterli stokta değil.",
                        ]);
                    }

                    $productCodes = array_values(array_filter([
                        ...$productCodes,
                        $variant->sku,
                        $variant->barcode,
                    ]));

                    if (BlacklistEntry::query()
                        ->where('type', 'product')
                        ->whereIn('value', $productCodes)
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'items' => "{$product->title} kara listede olduğu için sipariş edilemiyor.",
                        ]);
                    }

                    $variant->decrement('stock', $item['quantity']);
                } else {
                    if (! empty($item['product_variant_id'])) {
                        throw ValidationException::withMessages([
                            'items' => "{$product->title} varyantsız bir üründür.",
                        ]);
                    }

                    if ($product->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "{$product->title} için yeterli stok yok.",
                        ]);
                    }

                    if (BlacklistEntry::query()
                        ->where('type', 'product')
                        ->whereIn('value', $productCodes)
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'items' => "{$product->title} kara listede olduğu için sipariş edilemiyor.",
                        ]);
                    }

                    $product->decrement('stock', $item['quantity']);
                }

                $unitPriceCents = (int) round(((float) $product->price + (float) ($variant?->price_diff ?? 0)) * 100);
                if ($unitPriceCents < 0) {
                    throw ValidationException::withMessages([
                        'items' => "{$product->title} için geçersiz fiyat hesaplandı.",
                    ]);
                }

                $lineTotalCents = $unitPriceCents * $item['quantity'];
                $subtotalCents += $lineTotalCents;
                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'stock_code' => $variant?->sku ?: $product->stock_code,
                    'product_title' => $product->title,
                    'variant_text' => $variant?->full_name,
                    'quantity' => $item['quantity'],
                    'unit_price' => number_format($unitPriceCents / 100, 2, '.', ''),
                    'total_price' => number_format($lineTotalCents / 100, 2, '.', ''),
                ];
            }

            $totalCents = $subtotalCents;
            $subtotal = number_format($subtotalCents / 100, 2, '.', '');
            $total = number_format($totalCents / 100, 2, '.', '');
            $order = Order::create([
                'dealer_id' => $lockedDealer->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_city' => $data['customer_city'],
                'customer_district' => $data['customer_district'] ?? null,
                'customer_address' => $data['customer_address'],
                'customer_email' => $data['customer_email'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => 0,
                'total' => $total,
                'status' => 'pending',
                'dealer_note' => $data['dealer_note'] ?? null,
                'paid_at' => null,
            ]);

            foreach ($orderItems as $oi) {
                $oi['order_id'] = $order->id;
                OrderItem::create($oi);
            }


            return $order;
        });

        Cache::forget('xml_feed_catalog');

        return redirect()->route('dealer.orders.show', $order)
            ->with('success', 'Sipariş oluşturuldu. Kargo takip numarası ve PDF bilgilerini gönderdiğinizde bakiye düşülecektir.');
    }

    public function show(Order $order)
    {
        abort_unless($order->dealer_id === auth()->user()->dealer->id, 404);
        $order->load('items');

        return view('dealer.orders.show', compact('order'));
    }

    public function uploadCargo(\Illuminate\Http\Request $request, \App\Models\Order $order)
    {
        if ($order->dealer_id !== auth()->user()->dealer->id) {
            abort(403);
        }

        $data = $request->validate([
            'tracking_number' => 'required|string|max:100',
            'cargo_company' => 'nullable|string|max:100',
            'cargo_pdf' => 'nullable|file|mimes:pdf|max:10240',
            'payment_proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $updates = [
            'tracking_number' => $data['tracking_number'],
            'cargo_company' => $data['cargo_company'] ?? $order->cargo_company,
            'cargo_label_uploaded_at' => now(),
            'status' => in_array($order->status, ['pending', 'paid']) ? 'preparing' : $order->status,
        ];

        if ($request->hasFile('cargo_pdf')) {
            $updates['cargo_pdf_path'] = $request->file('cargo_pdf')->store('cargo-labels', 'local');
        }
        if ($request->hasFile('payment_proof')) {
            $updates['payment_proof_path'] = $request->file('payment_proof')->store('payment-proofs', 'local');
        }

        DB::transaction(function () use ($order, $updates): void {
            $lockedOrder = Order::query()->lockForUpdate()->with('dealer')->findOrFail($order->id);
            if ($lockedOrder->status === 'pending') {
                $dealer = Dealer::query()->lockForUpdate()->findOrFail($lockedOrder->dealer_id);
                $totalCents = (int) round((float) $lockedOrder->total * 100);
                $balanceCents = (int) round((float) $dealer->balance * 100);
                if ($balanceCents < $totalCents) {
                    throw ValidationException::withMessages(['balance' => 'Bakiyeniz yetersiz. Kargo bilgisi kaydedilmedi.']);
                }
                $newBalance = number_format(($balanceCents - $totalCents) / 100, 2, '.', '');
                $dealer->update(['balance' => $newBalance]);
                $lockedOrder->update(array_merge($updates, ['status' => 'preparing', 'paid_at' => now()]));
                BalanceTransaction::create([
                    'dealer_id' => $dealer->id,
                    'order_id' => $lockedOrder->id,
                    'type' => 'order_payment',
                    'amount' => '-'.$lockedOrder->total,
                    'balance_after' => $newBalance,
                    'description' => 'Sipariş ödemesi: '.$lockedOrder->order_number,
                ]);
            } else {
                $lockedOrder->update($updates);
            }
        });

        return back()->with('success', 'Kargo bilgileri iletildi. Siparişiniz işleme alınacak.');
    }
}
