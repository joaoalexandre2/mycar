<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrdemServicoRequest extends FormRequest
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

            'status' => [
                'required',
                'in:aberta,em_andamento,aguardando_peca,finalizada,cancelada',
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

            'data_fechamento' => [
                'nullable',
                'date',
                'after_or_equal:data_abertura',
            ],
        ];
    }
}