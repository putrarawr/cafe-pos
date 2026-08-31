<?php

namespace Database\Seeders;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Admin Users
        User::firstOrCreate(
            ['email' => 'adminkasir@pkl.com'],
            [
                'name' => 'Kasir Toko',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'botsekolahku@gmail.com'],
            [
                'name' => 'Manager',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Karyawan Kasir
        $karyawans = [
            [
                'nama_karyawan' => 'Budi Santoso',
                'email' => 'budi@pkl.com',
                'password' => Hash::make('password'),
                'no_telp' => '081987654321',
                'alamat' => 'Jl. Pahlawan No. 10, Surabaya',
            ],
            [
                'nama_karyawan' => 'Siti Aminah',
                'email' => 'siti@pkl.com',
                'password' => Hash::make('password'),
                'no_telp' => '081987654322',
                'alamat' => 'Jl. Diponegoro No. 25, Sidoarjo',
            ],
            [
                'nama_karyawan' => 'Rudi Hermawan',
                'email' => 'rudi@pkl.com',
                'password' => Hash::make('password'),
                'no_telp' => '081987654323',
                'alamat' => 'Jl. Veteran No. 5, Malang',
            ],
        ];

        foreach ($karyawans as $k) {
            Karyawan::firstOrCreate(['email' => $k['email']], $k);
        }
    }
}
