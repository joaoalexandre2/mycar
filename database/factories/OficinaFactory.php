<?php

namespace Database\Factories;

use App\Models\Oficina;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Oficina>
 */
class OficinaFactory extends Factory
{
    protected $model = Oficina::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->company(),
        ];
    }
}
