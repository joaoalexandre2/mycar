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
            // Recente de propósito: carro com mais de 20 anos é isento de IPVA (EC 137/2025),
            // o que muda vencimento e alertas. Teste de veículo antigo define o ano na mão.
            'ano' => fake()->numberBetween((int) date('Y') - 15, (int) date('Y')),
        ];
    }
}
