<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
                'exists:veiculos,id',
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