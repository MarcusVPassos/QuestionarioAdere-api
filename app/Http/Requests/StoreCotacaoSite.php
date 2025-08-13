<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCotacaoSite extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // pessoa: "Física"|"Jurídica" OU já normalizado "pf"|"pj"
            'pessoa'          => ['required', 'string'],
            'nome'            => ['required','string','min:3'],
            'telefone'        => ['required','string','min:8'],
            'email'           => ['required','email'],
            'origem'          => ['nullable','string'],
            'mensagem'        => ['nullable','string'],

            'cpf'             => ['nullable','string'],
            'cnpj'            => ['nullable','string'],

            'tipo_veiculo'    => ['nullable','string'],
            'modelo_veiculo'  => ['nullable','string'],
            'data_precisa'    => ['nullable','date'], // ISO 8601 (YYYY-MM-DD)
            'tempo_locacao'   => ['nullable','string'],

            // opcional: payload bruto do site
            'raw'             => ['sometimes','array'],
        ];
    }
}
