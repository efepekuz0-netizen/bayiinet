<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceTransaction;
use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DealerController extends Controller
{
    public function index(Request $request)
    {
        $query = Dealer::with('user')->latest();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($q = trim((string) $request->get('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('company_name', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
            });
        }

        $dealers = $query->paginate(24)->withQueryString();

        return view('admin.dealers.index', compact('dealers'));
    }

    public function show(Dealer $dealer)
    {
        $dealer->load(['user', 'orders' => fn ($q) => $q->latest()->take(20)]);
        $transactions = $dealer->balanceTransactions()->latest()->take(20)->get();

        return view('admin.dealers.show', compact('dealer', 'transactions'));
    }

    public function approve(Dealer $dealer)
    {
        try {
            $payload = [
                'status' => 'active',
                'approved_at' => now(),
            ];

            // Kolon varsa temizle (eski DB'lerde olmayabilir)
            if (\Illuminate\Support\Facades\Schema::hasColumn('dealers', 'suspended_at')) {
                $payload['suspended_at'] = null;
            }
            if (empty($dealer->integration_api_key)
                && \Illuminate\Support\Facades\Schema::hasColumn('dealers', 'integration_api_key')) {
                $payload['integration_api_key'] = Str::random(48);
            }
            if (empty($dealer->xml_token)) {
                $payload['xml_token'] = Str::random(40);
            }

            $dealer->update($payload);

            Cache::forget('xml_feed_dealer_'.$dealer->id);
            Cache::forget('xml_feed_'.$dealer->id);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Onay başarısız: '.$e->getMessage());
        }

        return back()->with('success', 'Bayi onaylandı. Artık giriş yapıp sipariş verebilir.');
    }

    public function suspend(Dealer $dealer)
    {
        try {
            $payload = ['status' => 'suspended'];
            if (\Illuminate\Support\Facades\Schema::hasColumn('dealers', 'suspended_at')) {
                $payload['suspended_at'] = now();
            }
            $dealer->update($payload);
            Cache::forget('xml_feed_dealer_'.$dealer->id);
            Cache::forget('xml_feed_'.$dealer->id);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Askıya alma başarısız: '.$e->getMessage());
        }

        return back()->with('success', 'Bayi askıya alındı.');
    }

    public function addBalance(Request $request, Dealer $dealer)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($dealer, $data) {
            $lockedDealer = Dealer::query()->lockForUpdate()->findOrFail($dealer->id);
            $newBalance = $lockedDealer->balance + $data['amount'];

            $lockedDealer->update(['balance' => $newBalance]);

            BalanceTransaction::create([
                'dealer_id' => $lockedDealer->id,
                'type' => 'deposit',
                'amount' => $data['amount'],
                'balance_after' => $newBalance,
                'description' => $data['description'] ?? 'Manuel bakiye yükleme',
                'created_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Bakiye eklendi.');
    }

    /**
     * Bayi hesap / kar / entegrasyon ayarları
     */
    public function update(Request $request, Dealer $dealer)
    {
        $data = $request->validate([
            'company_name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'tax_number' => 'nullable|string|max:30',
            'tax_office' => 'nullable|string|max:100',
            'default_marketplace_margin' => 'nullable|numeric|min:0|max:500',
            'auto_sync_enabled' => 'nullable|boolean',
            'admin_note' => 'nullable|string|max:2000',
            'trendyol_seller_id' => 'nullable|string|max:50',
            'trendyol_api_key' => 'nullable|string|max:255',
            'trendyol_api_secret' => 'nullable|string|max:255',
            'regenerate_api_key' => 'nullable|boolean',
            'regenerate_xml_token' => 'nullable|boolean',
        ]);

        $update = [];

        foreach (['company_name', 'phone', 'city', 'district', 'address', 'tax_number', 'tax_office', 'admin_note', 'trendyol_seller_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (array_key_exists('default_marketplace_margin', $data) && $data['default_marketplace_margin'] !== null) {
            $update['default_marketplace_margin'] = $data['default_marketplace_margin'];
        }

        $update['auto_sync_enabled'] = $request->boolean('auto_sync_enabled');

        // Trendyol kimlik bilgileri (sadece dolu alanlar güncellenir)
        $credentials = $dealer->trendyol_credentials ?? [];
        $keyChanged = false;
        if (! empty($data['trendyol_api_key'])) {
            $credentials['api_key'] = $data['trendyol_api_key'];
            $keyChanged = true;
        }
        if (! empty($data['trendyol_api_secret'])) {
            $credentials['api_secret'] = $data['trendyol_api_secret'];
            $keyChanged = true;
        }
        if ($keyChanged || array_key_exists('trendyol_seller_id', $data)) {
            $update['trendyol_credentials'] = $credentials;
            $update['trendyol_last_error'] = null;
        }

        if ($request->boolean('regenerate_api_key')) {
            $update['integration_api_key'] = Str::random(48);
        }

        if ($request->boolean('regenerate_xml_token')) {
            $update['xml_token'] = Str::random(40);
        }

        $dealer->update($update);

        // Kar veya token değiştiyse feed cache temizle
        Cache::forget('xml_feed_dealer_'.$dealer->id);
        Cache::forget('xml_feed_'.$dealer->id);

        return back()->with('success', 'Bayi ayarları kaydedildi.');
    }
}
