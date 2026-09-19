<?php

namespace Database\Factories;

use App\Models\OrdemServico;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrdemServico>
 */
class OrdemServicoFactory extends Factory
{
    protected $model = OrdemServico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'veiculo_id' => Veiculo::factory(),
            'descricao' => fake()->sentence(),
            'status' => 'aberta',
            'valor' => fake()->randomFloat(2, 50, 2000),
            'data_abertura' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'data_fechamento' => null,
        ];
    }
}
