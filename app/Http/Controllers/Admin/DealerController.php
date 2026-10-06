<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceTransaction;
use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealerController extends Controller
{
    public function index(Request $request)
    {
        $query = Dealer::with('user')->latest();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $dealers = $query->paginate(20)->withQueryString();

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
        $dealer->update([
            'status' => 'active',
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Bayi onaylandı.');
    }

    public function suspend(Dealer $dealer)
    {
        $dealer->update(['status' => 'suspended']);

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
}
