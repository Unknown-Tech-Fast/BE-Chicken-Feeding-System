<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('INITIAL_ADMIN_NAME');
        $email = env('INITIAL_ADMIN_EMAIL');
        $password = env('INITIAL_ADMIN_PASSWORD');

        if (!$name || !$email || !$password) {
            throw new RuntimeException(
                'Lengkapi INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL, '
                . 'dan INITIAL_ADMIN_PASSWORD pada file .env.'
            );
        }

        if (strlen($password) < 12) {
            throw new RuntimeException(
                'Password admin harus minimal 12 karakter.'
            );
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            throw new RuntimeException(
                'Email admin sudah terdaftar. Seeder dihentikan '
                . 'agar password akun yang ada tidak tertimpa.'
            );
        }

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->role = 'admin';
        $user->save();

        $this->command?->info(
            "Admin awal berhasil dibuat: {$email}"
        );
    }
}
