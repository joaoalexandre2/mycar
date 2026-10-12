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
                Rule::unique('clientes', 'cpf')
                    ->where('oficina_id', app('oficina.atual'))
                    ->ignore($this->route('id')),
            ],

            'telefone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('clientes', 'telefone')
                    ->where('oficina_id', app('oficina.atual'))
                    ->ignore($this->route('id')),
            ],

            'email' => 'nullable|email|max:255',

            'ativo' => 'boolean',
        ];
    }
}
