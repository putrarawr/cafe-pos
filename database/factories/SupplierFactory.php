<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_supplier' => fake()->company(),
            'no_telepon' => fake()->phoneNumber(),
            'alamat' => fake()->address(),
            'status' => 'aktif',
        ];
    }
}
