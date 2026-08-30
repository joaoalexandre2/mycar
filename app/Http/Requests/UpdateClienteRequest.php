<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',

            'cpf' => [
                'required',
                'digits:11',
                Rule::unique('clientes')->ignore($this->route('id'))
            ],

            'telefone' => 'required|string|max:20',

            'ativo' => 'boolean'
        ];
    }
}