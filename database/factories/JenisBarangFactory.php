<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisBarangFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_jenis' => fake()->word(),
            'deskripsi' => fake()->sentence(),
        ];
    }
}
