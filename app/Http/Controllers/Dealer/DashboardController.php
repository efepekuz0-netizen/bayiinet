<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Models\DealerAnnouncement;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class DashboardController extends Controller
{
    public function account()
    {
        $dealer = auth()->user()->dealer;
        $recentOrders = Order::where('dealer_id', $dealer->id)->latest()->take(10)->get();

        return view('dealer.account', compact('dealer', 'recentOrders'));
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $dealer = $user->dealer;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'tax_number' => 'nullable|string|max:20',
            'tax_office' => 'nullable|string|max:100',
        ]);

        $user->update(['name' => $data['name']]);
        $dealer->update([
            'phone' => $data['phone'] ?? $dealer->phone,
            'city' => $data['city'] ?? $dealer->city,
            'district' => $data['district'] ?? $dealer->district,
            'address' => $data['address'] ?? $dealer->address,
            'tax_number' => $data['tax_number'] ?? $dealer->tax_number,
            'tax_office' => $data['tax_office'] ?? $dealer->tax_office,
        ]);

        return back()->with('success', 'Hesap bilgileri güncellendi.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = auth()->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Mevcut şifre hatalı.'])->withInput();
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Şifreniz güncellendi.');
    }

    public function uploadTaxDocument(Request $request)
    {
        $request->validate([
            'tax_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $dealer = auth()->user()->dealer;
        $path = $request->file('tax_document')->store('dealer-tax/'.$dealer->id, 'public');

        if ($dealer->tax_document_path) {
            Storage::disk('public')->delete($dealer->tax_document_path);
        }

        $dealer->update(['tax_document_path' => $path]);

        return back()->with('success', 'Vergi levhası yüklendi.');
    }

    public function index()
    {
        try {
            $dealer = auth()->user()->dealer;
            if (! $dealer) {
                return redirect()->route('dealer.application');
            }

            $stats = [
                'balance' => $dealer->balance,
                'orders' => Order::where('dealer_id', $dealer->id)->count(),
                'pending_orders' => Order::where('dealer_id', $dealer->id)->whereIn('status', ['pending', 'paid', 'preparing'])->count(),
                'products' => Product::where('is_active', true)->count(),
            ];

            $recentOrders = Order::where('dealer_id', $dealer->id)->latest()->take(8)->get();
            $announcements = collect();
            try {
                $announcements = DealerAnnouncement::query()
                    ->where('is_active', true)
                    ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                    ->latest()
                    ->take(5)
                    ->get();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('dealer announcements failed', ['error' => $e->getMessage()]);
            }

            return view('dealer.dashboard', compact('dealer', 'stats', 'recentOrders', 'announcements'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('dealer dashboard failed', ['error' => $e->getMessage()]);

            return redirect()->route('home')->with('error', 'Panel yüklenemedi: '.$e->getMessage());
        }
    }
}
