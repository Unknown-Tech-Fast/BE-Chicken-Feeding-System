<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class OperatorUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('INITIAL_OPERATOR_NAME');
        $email = env('INITIAL_OPERATOR_EMAIL');
        $password = env('INITIAL_OPERATOR_PASSWORD');

        if (!$name || !$email || !$password) {
            throw new RuntimeException(
                'Lengkapi INITIAL_OPERATOR_* di file .env.'
            );
        }

        if (strlen($password) < 12) {
            throw new RuntimeException(
                'Password Operator harus minimal 12 karakter.'
            );
        }

        if (User::where('email', $email)->exists()) {
            throw new RuntimeException(
                'Email Operator sudah terdaftar. Seeder dihentikan.'
            );
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'operator';
        $user->save();

        $this->command?->info(
            "Akun Operator berhasil dibuat: {$email}"
        );
    }
}
