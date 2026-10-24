<?php

namespace App\Http\Requests;

use App\Enums\TipoVeiculo;
use App\Rules\AnoFabricacao;
use App\Rules\PlacaBrasileira;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras de cadastro e edição de veículo da oficina. Na edição, a rota traz o
 * id e a placa pode continuar sendo a do próprio veículo.
 */
class SalvarVeiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['placa' => PlacaBrasileira::normalizar($this->input('placa'))]);
    }

    public function rules(): array
    {
        return [
            'cliente_id' => [
                'required',
                Rule::exists('clientes', 'id')->where('oficina_id', app('oficina.atual')),
            ],
            'placa' => [
                'required',
                'string',
                new PlacaBrasileira,
                // Única dentro da oficina: outra oficina pode ter o mesmo carro.
                Rule::unique('veiculos', 'placa')
                    ->where('oficina_id', app('oficina.atual'))
                    ->ignore($this->route('id')),
            ],
            'tipo_veiculo' => ['sometimes', Rule::in(TipoVeiculo::valores())],
            'marca' => ['required', 'string', 'max:100'],
            'modelo' => ['required', 'string', 'max:100'],
            'ano' => ['required', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'ano_fabricacao' => [
                'nullable',
                'integer',
                'min:1900',
                'max:' . date('Y'),
                new AnoFabricacao($this->input('ano')),
            ],
            'uf' => ['nullable', 'string', 'size:2', Rule::in(array_keys(config('tributos.estados')))],
            'fipe_marca_id' => ['nullable', 'integer'],
            'fipe_modelo_id' => ['nullable', 'integer'],
            'fipe_ano' => ['nullable', 'string', 'max:10'],
        ];
    }
}
