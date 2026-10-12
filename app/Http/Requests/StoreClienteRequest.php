<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
       
        return [
            'nome' => 'required|string|max:255',
            // Único dentro da oficina: outra oficina pode ter o mesmo cliente.
            'cpf' => [
                'required',
                'digits:11',
                Rule::unique('clientes', 'cpf')->where('oficina_id', app('oficina.atual')),
            ],
            'telefone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('clientes', 'telefone')->where('oficina_id', app('oficina.atual')),
            ],
            'email' => 'nullable|email|max:255',
            'ativo' => 'boolean'
        ];
        
    }
    public function messages(): array
{
    return [
        'nome.required' => 'O nome é obrigatório.',

        'cpf.required' => 'O CPF é obrigatório.',
        'cpf.unique' => 'Já existe um cliente com esse CPF.',
        'cpf.size' => 'O CPF deve possuir 11 números.',

        'telefone.required' => 'O telefone é obrigatório.',
        'telefone.unique' => 'Já existe um cliente com esse telefone.',
    ];
}
}
