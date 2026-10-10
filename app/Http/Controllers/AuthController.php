<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-posta veya şifre hatalı.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        try {
            $user = Auth::user();

            if ($user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }

            if ($user->isDealer()) {
                try {
                    $dealer = $user->dealer;
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('login dealer load failed', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);

                    return redirect()->route('home')
                        ->with('error', 'Hesap bilgileri yüklenemedi. Destek ile iletişime geçin.');
                }

                if (! $dealer || $dealer->status !== 'active') {
                    return redirect()->route('dealer.application');
                }

                $intended = $request->query('redirect') ?: $request->input('redirect');
                if (is_string($intended) && str_starts_with($intended, url('/'))) {
                    return redirect()->to($intended);
                }

                return redirect()->intended(route('dealer.dashboard'));
            }

            return redirect()->intended(route('home'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('login redirect failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Oturum açıldı ama yönlendirme patladıysa en azından ana sayfaya düş
            return redirect()->route('home')
                ->with('error', 'Giriş yapıldı ancak panele yönlendirme başarısız. Ana sayfadan devam edin.');
        }
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
            'password' => ['required', 'confirmed', Password::min(8)],
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
                'xml_token' => str_replace('-', '', (string) Str::uuid()).Str::random(16),
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
