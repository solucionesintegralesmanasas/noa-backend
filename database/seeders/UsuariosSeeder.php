<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UsuariosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'mdarwin1992@hotmail.com'],
            [
                'name' => 'Darwin Montes Lopez',
                'user_name' => 'mdarwin1992',
                'email_verified_at' => now(),
                'password' => bcrypt('123456789'),
                'uuid' => Str::uuid()->toString(),
            ]
        );

        $superAdmin->assignRole('SUPERADMIN');
    }
}
