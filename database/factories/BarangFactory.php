<?php

namespace Database\Factories;

use App\Models\JenisBarang;
use Illuminate\Database\Eloquent\Factories\Factory;

class BarangFactory extends Factory
{
    public function definition(): array
    {
        return [
            'jenis_barang_id' => JenisBarang::factory(),
            'nama_barang' => fake()->words(2, true),
            'harga_beli' => fake()->numberBetween(1000, 100000),
            'harga_jual' => fake()->numberBetween(1000, 150000),
        ];
    }
}
