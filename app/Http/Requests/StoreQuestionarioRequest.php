<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionarioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $tipoVenda = $this->input('tipo_venda');

        $regrasTipo = match ($tipoVenda) {
            'assinatura' => [
                'valor_venda_total' => ['required','numeric','min:0'],
                'valor_mensalidade' => ['required','numeric','min:0'],
                'valor_venda' => ['nullable','numeric','min:0'],
            ],
            'diario','mensal' => [
                'valor_venda' => ['required','numeric','min:0'],
                'valor_venda_total' => ['nullable','numeric','min:0'],
                'valor_mensalidade' => ['nullable','numeric','min:0'],
            ],
            default => [
                // permitir nulo para retrocompatibilidade quando ainda não enviado
                'valor_venda' => ['nullable','numeric','min:0'],
                'valor_venda_total' => ['nullable','numeric','min:0'],
                'valor_mensalidade' => ['nullable','numeric','min:0'],
            ],
        };

        return array_merge([
            'dados' => ['required','json'],
            'tipo_venda' => ['nullable','in:diario,mensal,assinatura'],
            'documentos.*' => ['file','max:5120'],
        ], $regrasTipo);
    }
}
