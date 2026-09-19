<?php

namespace Database\Factories;

use App\Models\Manutencao;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manutencao>
 */
class ManutencaoFactory extends Factory
{
    protected $model = Manutencao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'veiculo_id' => Veiculo::factory(),
            'tipo' => fake()->randomElement(['Troca de óleo', 'Revisão', 'Alinhamento', 'Freios']),
            'descricao' => fake()->sentence(),
            'valor' => fake()->randomFloat(2, 50, 1000),
            'data_manutencao' => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'quilometragem' => fake()->numberBetween(1000, 150000),
            'proxima_data' => null,
            'proxima_quilometragem' => null,
        ];
    }
}
