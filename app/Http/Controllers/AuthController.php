<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            if ($user->isDealer()) {
                // Bayi profili yoksa veya askıdaysa başvuru sayfası
                $dealer = $user->dealer;
                if (! $dealer || $dealer->status !== 'active') {
                    return redirect()->route('dealer.application');
                }

                return redirect()->intended(route('dealer.dashboard'));
            }

            return redirect()->intended(route('home'));
        }

        return back()->withErrors(['email' => 'E-posta veya şifre hatalı.'])->onlyInput('email');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'company_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // 'hashed' cast ile hashlenir
                'role' => 'dealer',
            ]);

            Dealer::create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'],
                'phone' => $data['phone'] ?? null,
                'city' => $data['city'] ?? null,
                'xml_token' => Str::random(48),
                'status' => 'pending',
                'balance' => 0,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dealer.application')
            ->with('success', 'Kayıt başarılı! Bayilik onayınız bekleniyor.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
