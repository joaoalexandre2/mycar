<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Veiculo>
 */
class VeiculoFactory extends Factory
{
    protected $model = Veiculo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'placa' => strtoupper(fake()->unique()->bothify('???#?##')),
            'marca' => fake()->randomElement(['Fiat', 'Volkswagen', 'Chevrolet', 'Toyota', 'Honda']),
            'modelo' => fake()->word(),
            'ano' => fake()->numberBetween(1990, (int) date('Y')),
        ];
    }
}
