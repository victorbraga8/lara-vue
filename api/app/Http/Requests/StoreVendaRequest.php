<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente'  => ['required', 'string', 'min:2', 'max:150'],
            'produtos' => ['required', 'array', 'min:1'],

            'produtos.*.id'             => ['required', 'integer', 'exists:produtos,id'],
            'produtos.*.quantidade'     => ['required', 'integer', 'min:1', 'max:1000000'],
            'produtos.*.preco_unitario' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente.required' => 'Informe o cliente.',
            'produtos.required'=> 'Informe ao menos um item.',
            'produtos.min'     => 'Informe ao menos um item.',
        ];
    }
}
