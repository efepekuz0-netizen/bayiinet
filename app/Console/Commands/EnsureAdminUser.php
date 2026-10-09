<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class EnsureAdminUser extends Command
{
    protected $signature = 'bayiinet:ensure-admin';

    protected $description = 'ADMIN_EMAIL ve ADMIN_PASSWORD ortam değişkenlerinden ilk yönetici hesabını oluşturur.';

    public function handle(): int
    {
        // env() yerine config() kullanılır: config:cache uygulandığında env() null döner
        // ve yönetici hesabı sessizce oluşmaz.
        $email = mb_strtolower(trim((string) config('bayiinet.admin.email', '')));
        $password = (string) config('bayiinet.admin.password', '');
        $minLength = (int) config('bayiinet.admin.min_password_length', 8);

        if ($email === '' || $password === '') {
            $this->info('ADMIN_EMAIL / ADMIN_PASSWORD tanımlı değil, yönetici oluşturma atlandı.');

            return self::SUCCESS;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('ADMIN_EMAIL geçerli bir e-posta adresi değil.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            if (mb_strlen($password) < $minLength) {
                $this->error("ADMIN_PASSWORD en az {$minLength} karakter olmalı.");

                return self::FAILURE;
            }

            User::create([
                'name' => 'Yönetici',
                'email' => $email,
                'password' => $password, // User modelindeki "hashed" cast şifreler
                'role' => 'admin',
            ]);
            $this->info("Yönetici oluşturuldu: {$email}");

            return self::SUCCESS;
        }

        // Hesap zaten var: şifresine dokunmadan sadece yönetici rolünü garanti eder.
        if ($user->role !== 'admin') {
            $user->update(['role' => 'admin']);
            $this->info("{$email} hesabına yönetici rolü verildi.");
        } else {
            $this->info("Yönetici zaten mevcut: {$email}");
        }

        return self::SUCCESS;
    }
}
