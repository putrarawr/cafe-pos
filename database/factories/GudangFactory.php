<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GudangFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_gudang' => fake()->company(),
            'alamat' => fake()->address(),
        ];
    }
}
