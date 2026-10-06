<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MarketplaceApiException;
use App\Http\Controllers\Controller;
use App\Jobs\SyncTrendyolCatalog;
use App\Models\MarketplaceConnection;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceSyncRun;
use App\Models\Source;
use App\Services\TrendyolMarketplaceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

class MarketplaceController extends Controller
{
    public function index(): View
    {
        $connection = MarketplaceConnection::query()->where('provider', 'trendyol')->first();
        $sources = Source::query()->withCount('products')->orderBy('name')->get();
        $recentRun = $connection?->syncRuns()->with('source')->latest()->first();
        $orders = MarketplaceOrder::query()
            ->where('marketplace_connection_id', $connection?->id ?? 0)
            ->latest('ordered_at')
            ->latest('id')
            ->paginate(20);
        $marketplaces = [
            ['name' => 'Trendyol', 'available' => true, 'connected' => $connection?->last_connected_at !== null],
            ['name' => 'Hepsiburada', 'available' => false, 'connected' => false],
            ['name' => 'N11', 'available' => false, 'connected' => false],
            ['name' => 'Amazon', 'available' => false, 'connected' => false],
            ['name' => 'ÇiçekSepeti', 'available' => false, 'connected' => false],
        ];

        return view('admin.marketplace.index', compact(
            'connection',
            'marketplaces',
            'orders',
            'recentRun',
            'sources',
        ));
    }

    public function updateTrendyol(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'string', 'max:32', 'regex:/^\d+$/'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'api_secret' => ['nullable', 'string', 'max:255'],
            'barcode_prefix' => ['required', 'string', 'max:32'],
            'profit_margin' => ['required', 'numeric', 'between:0,100'],
            'commission_rate' => ['required', 'numeric', 'between:0,99.99'],
            'min_profit' => ['required', 'numeric', 'min:0', 'max:100000'],
            'platform_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'round_to' => ['required', 'numeric', 'gt:0', 'lt:1'],
            'default_desi' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'delivery_type' => ['required', Rule::in(['standart', 'hizli'])],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        if (($data['min_price'] ?? 0) > 0
            && ($data['max_price'] ?? 0) > 0
            && (float) $data['max_price'] < (float) $data['min_price']) {
            throw ValidationException::withMessages([
                'max_price' => 'En yüksek fiyat, en düşük fiyattan az olamaz.',
            ]);
        }

        $connection = MarketplaceConnection::query()->firstOrNew(['provider' => 'trendyol']);
        $credentials = $connection->credentials ?? [];
        $previousCredentials = $credentials;
        foreach (['api_key', 'api_secret'] as $key) {
            if (isset($data[$key]) && trim($data[$key]) !== '') {
                $credentials[$key] = trim($data[$key]);
            }
        }

        if (empty($credentials['api_key']) || empty($credentials['api_secret'])) {
            throw ValidationException::withMessages([
                'api_key' => 'Trendyol API anahtarı ve sırrı gereklidir.',
            ]);
        }

        $requiresConnectionTest = ! $connection->exists
            || $connection->account_id !== $data['account_id']
            || $credentials !== $previousCredentials;

        $connection->fill([
            'name' => 'Trendyol',
            'account_id' => $data['account_id'],
            'credentials' => $credentials,
            'last_connected_at' => $requiresConnectionTest ? null : $connection->last_connected_at,
            'last_error' => $requiresConnectionTest ? null : $connection->last_error,
            'options' => [
                'barcode_prefix' => trim($data['barcode_prefix']),
                'profit_margin' => (float) $data['profit_margin'] / 100,
                'commission_rate' => (float) $data['commission_rate'] / 100,
                'min_profit' => (float) $data['min_profit'],
                'platform_fee' => (float) $data['platform_fee'],
                'round_to' => (float) $data['round_to'],
                'default_desi' => (float) $data['default_desi'],
                'delivery_type' => $data['delivery_type'],
                'min_price' => (float) ($data['min_price'] ?? 0),
                'max_price' => (float) ($data['max_price'] ?? 0),
            ],
        ]);
        $connection->save();

        return back()->with('success', 'Trendyol bağlantı ve fiyatlandırma ayarları şifreli olarak kaydedildi.');
    }

    public function testTrendyolConnection(TrendyolMarketplaceService $marketplace): RedirectResponse
    {
        $connection = $this->trendyolConnection();

        try {
            $result = $marketplace->testConnection($connection);
        } catch (MarketplaceApiException|ConnectionException|LogicException $exception) {
            $connection->update(['last_error' => $exception->getMessage()]);

            return back()->with('error', 'Trendyol bağlantısı doğrulanamadı: '.$exception->getMessage());
        }

        $connection->update([
            'last_connected_at' => now(),
            'last_error' => null,
        ]);
        $productCount = (int) ($result['totalElements'] ?? count($result['content'] ?? []));

        return back()->with('success', 'Trendyol API bağlantısı doğrulandı. Mağazada yaklaşık '.number_format($productCount).' onaylı ilan var.');
    }

    public function syncTrendyolProducts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'integer', 'exists:sources,id'],
        ]);
        $connection = $this->trendyolConnection();
        if ($connection->last_connected_at === null) {
            return back()->with('error', 'Ürün eşitlemeden önce Trendyol bağlantısını test edin.');
        }
        if ($connection->syncRuns()->where('sync_type', 'products')->whereIn('status', ['queued', 'processing'])->exists()) {
            return back()->with('error', 'Trendyol ürün senkronizasyonu zaten kuyrukta veya çalışıyor.');
        }

        $run = $connection->syncRuns()->create([
            'source_id' => $data['source_id'],
            'sync_type' => 'products',
            'status' => 'queued',
            'message' => 'Trendyol onaylı ilanları ile XML ürünleri eşleştiriliyor.',
        ]);

        SyncTrendyolCatalog::dispatch($run->id);

        return back()->with('success', 'Mevcut Trendyol ilanlarının fiyat/stok senkronizasyonu kuyruğa alındı. Kuyruk işleyicisinin çalışıyor olması gerekir.');
    }

    public function syncTrendyolOrders(TrendyolMarketplaceService $marketplace): RedirectResponse
    {
        $connection = $this->trendyolConnection();
        if ($connection->last_connected_at === null) {
            return back()->with('error', 'Siparişleri almadan önce Trendyol bağlantısını test edin.');
        }

        $now = now();
        $windowStart = $connection->orders_has_more && $connection->orders_window_start
            ? $connection->orders_window_start
            : $now->copy()->subDays(14);
        $windowEnd = $connection->orders_has_more && $connection->orders_window_end
            ? $connection->orders_window_end
            : $now;
        $query = [
            'size' => 200,
            'lastModifiedStartDate' => $windowStart->getTimestamp() * 1000,
            'lastModifiedEndDate' => $windowEnd->getTimestamp() * 1000,
        ];

        if ($connection->orders_has_more && $connection->orders_cursor) {
            $query['nextCursor'] = $connection->orders_cursor;
        }

        try {
            $result = $marketplace->orderStream($connection, $query);
        } catch (MarketplaceApiException|ConnectionException|LogicException $exception) {
            $connection->update(['last_error' => $exception->getMessage()]);

            return back()->with('error', 'Trendyol siparişleri alınamadı: '.$exception->getMessage());
        }

        $packages = $result['content'] ?? null;
        if (! is_array($packages)) {
            return back()->with('error', 'Trendyol sipariş yanıtı beklenen biçimde değil; kayıtlar değiştirilmedi.');
        }

        $syncedCount = 0;
        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }

            $packageId = $package['shipmentPackageId'] ?? $package['packageId'] ?? null;
            if (! is_string($packageId) && ! is_int($packageId)) {
                continue;
            }

            $lines = $package['lines'] ?? [];
            $lastModified = $this->marketplaceDate($package['lastModifiedDate'] ?? null);
            MarketplaceOrder::query()->updateOrCreate(
                [
                    'marketplace_connection_id' => $connection->id,
                    'remote_package_id' => (string) $packageId,
                ],
                [
                    'order_number' => isset($package['orderNumber']) ? (string) $package['orderNumber'] : null,
                    'status' => (string) ($package['shipmentPackageStatus'] ?? $package['status'] ?? 'Unknown'),
                    'cargo_company' => $package['cargoProviderName'] ?? $package['cargoProvider'] ?? null,
                    'cargo_tracking_number' => isset($package['cargoTrackingNumber']) ? (string) $package['cargoTrackingNumber'] : null,
                    'cargo_tracking_link' => $this->secureTrackingLink($package['cargoTrackingLink'] ?? $package['cargoTrackingUrl'] ?? null),
                    'total_amount' => is_numeric($package['totalPrice'] ?? null) ? (float) $package['totalPrice'] : null,
                    'item_count' => is_array($lines) ? count($lines) : 0,
                    'ordered_at' => $this->marketplaceDate($package['orderDate'] ?? null),
                    'last_modified_at' => $lastModified,
                    'payload' => $package,
                ],
            );
            $syncedCount++;
        }

        $hasMore = (bool) ($result['hasMore'] ?? false);
        $nextCursor = $result['nextCursor'] ?? null;
        if ($hasMore && (! is_string($nextCursor) || $nextCursor === '')) {
            return back()->with('error', 'Trendyol daha fazla sipariş olduğunu bildirdi ancak devam imleci dönmedi.');
        }

        $connection->update([
            'orders_cursor' => $hasMore ? $nextCursor : null,
            'orders_window_start' => $hasMore ? $windowStart : null,
            'orders_window_end' => $hasMore ? $windowEnd : null,
            'orders_has_more' => $hasMore,
            'last_orders_synced_at' => $hasMore ? $connection->last_orders_synced_at : now(),
            'last_error' => null,
        ]);

        $message = number_format($syncedCount).' sipariş paketi güncellendi.';
        if ($hasMore) {
            $message .= ' Daha fazla kayıt var; sonraki sayfayı almak için tekrar tıklayın.';
        }

        return back()->with('success', $message);
    }

    public function updateTrendyolOrder(Request $request, MarketplaceOrder $order, TrendyolMarketplaceService $marketplace): RedirectResponse
    {
        abort_unless($order->connection?->provider === 'trendyol', 404);

        $connection = $order->connection;
        if ($connection->last_connected_at === null) {
            return back()->with('error', 'Paket durumunu göndermeden önce Trendyol bağlantısını test edin.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['Picking', 'Invoiced'])],
            'invoice_number' => ['required_if:status,Invoiced', 'nullable', 'string', 'max:100'],
        ]);
        $currentStatus = mb_strtolower($order->status);
        if (($data['status'] === 'Picking' && $currentStatus !== 'created')
            || ($data['status'] === 'Invoiced' && $currentStatus !== 'picking')) {
            return back()->with('error', 'Paket durumu sırası geçersiz. Önce Picking, ardından Invoiced gönderilmelidir.');
        }

        if (! ctype_digit($order->remote_package_id)) {
            return back()->with('error', 'Paket numarası Trendyol API biçimine uygun olmadığı için durum gönderilmedi.');
        }

        $invoiceNumber = $data['status'] === 'Invoiced' ? $data['invoice_number'] : null;
        try {
            $marketplace->updatePackageStatus($connection, $order->remote_package_id, $data['status'], $invoiceNumber);
        } catch (MarketplaceApiException|ConnectionException|LogicException $exception) {
            $connection->update(['last_error' => $exception->getMessage()]);

            return back()->with('error', 'Trendyol paket durumu güncellenemedi: '.$exception->getMessage());
        }

        $order->update([
            'status' => $data['status'],
            'invoice_number' => $invoiceNumber ?? $order->invoice_number,
        ]);

        return back()->with('success', 'Trendyol paket durumu güncellendi. Kargo takip bilgisi Trendyol siparişleri yenilendiğinde görünür.');
    }

    public function checkTrendyolBatch(Request $request, MarketplaceSyncRun $run, TrendyolMarketplaceService $marketplace): RedirectResponse
    {
        abort_unless($run->connection?->provider === 'trendyol', 404);
        if ($run->connection->last_connected_at === null) {
            return back()->with('error', 'Batch sonucunu almadan önce Trendyol bağlantısını test edin.');
        }

        $data = $request->validate([
            'batch_request_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        abort_unless(in_array($data['batch_request_id'], $run->batch_request_ids ?? [], true), 404);

        try {
            $result = $marketplace->batchResult($run->connection, $data['batch_request_id']);
        } catch (MarketplaceApiException|ConnectionException|LogicException $exception) {
            return back()->with('error', 'Trendyol batch sonucu alınamadı: '.$exception->getMessage());
        }

        $items = $result['items'] ?? [];
        $failed = collect($items)->where('status', 'FAILED')->count();
        $message = 'Batch sonucu: '.number_format(count($items)).' ürün, '.number_format($failed).' başarısız.';
        $failureReasons = collect($items)
            ->where('status', 'FAILED')
            ->flatMap(fn (array $item): array => $item['failureReasons'] ?? [])
            ->filter(fn (mixed $reason): bool => is_string($reason) && $reason !== '')
            ->unique()
            ->take(3)
            ->values()
            ->all();
        if ($failureReasons !== []) {
            $message .= ' Hata: '.implode(' | ', $failureReasons);
        }
        if (isset($result['status'])) {
            $message .= ' Durum: '.$result['status'].'.';
        }

        return back()->with($failed > 0 ? 'error' : 'success', $message);
    }

    private function trendyolConnection(): MarketplaceConnection
    {
        return MarketplaceConnection::query()->where('provider', 'trendyol')->firstOrFail();
    }

    private function marketplaceDate(mixed $value): ?Carbon
    {
        if (! is_numeric($value) && (! is_string($value) || trim($value) === '')) {
            return null;
        }

        return is_numeric($value)
            ? Carbon::createFromTimestampMs((int) $value)
            : Carbon::parse((string) $value);
    }

    private function secureTrackingLink(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $parts = parse_url($value);
        if (
            ! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
        ) {
            return null;
        }

        return $value;
    }
}
