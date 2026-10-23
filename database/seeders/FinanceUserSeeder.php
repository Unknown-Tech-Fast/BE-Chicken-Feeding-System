<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class FinanceUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('INITIAL_FINANCE_NAME');
        $email = env('INITIAL_FINANCE_EMAIL');
        $password = env('INITIAL_FINANCE_PASSWORD');

        if (!$name || !$email || !$password) {
            throw new RuntimeException(
                'Lengkapi konfigurasi INITIAL_FINANCE_* di file .env.'
            );
        }

        if (strlen($password) < 12) {
            throw new RuntimeException(
                'Password Finance harus minimal 12 karakter.'
            );
        }

        if (User::where('email', $email)->exists()) {
            throw new RuntimeException(
                'Email Finance sudah terdaftar. Seeder dihentikan.'
            );
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'finance';
        $user->save();

        $this->command?->info("Akun Finance berhasil dibuat: {$email}");
    }
}