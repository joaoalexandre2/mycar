<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'cpf' => 'required|digits:11|unique:clientes,cpf',
            'telefone' => 'required|string|max:20',
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
    ];
}
}
