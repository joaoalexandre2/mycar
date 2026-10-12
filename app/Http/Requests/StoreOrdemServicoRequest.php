<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrdemServicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'veiculo_id' => [
                'required',
                Rule::exists('veiculos', 'id')->where('oficina_id', app('oficina.atual')),
            ],

            'descricao' => [
                'required',
                'string',
            ],

            'valor' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'data_abertura' => [
                'required',
                'date',
            ],
        ];
    }
}
