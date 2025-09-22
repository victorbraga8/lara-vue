<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'        => ['required', 'string', 'min:3', 'unique:produtos,nome'],
            'preco_venda' => ['required', 'numeric', 'min:0.01'],
             'estoque'     => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required'        => 'O nome é obrigatório.',
            'nome.min'             => 'O nome deve ter ao menos 3 caracteres.',
            'preco_venda.required' => 'Informe o preço de venda sugerido.',
            'preco_venda.numeric'  => 'Preço de venda deve ser numérico.',
            'preco_venda.min'      => 'Preço de venda deve ser maior que zero.',
        ];
    }
}
