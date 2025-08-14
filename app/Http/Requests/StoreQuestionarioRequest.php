<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionarioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /**
     * Converte 'dados' de string JSON -> array antes da validação.
     */
    protected function prepareForValidation(): void
    {
        $dados = $this->input('dados');
        if (is_string($dados)) {
            $decoded = json_decode($dados, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['dados' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        $tipoVenda = $this->input('tipo_venda');

        $regrasTipo = match ($tipoVenda) {
            'assinatura' => [
                'valor_venda_total' => ['required','numeric','min:0'],
                'valor_mensalidade' => ['required','numeric','min:0'],
                'valor_venda'       => ['nullable','numeric','min:0'],
            ],
            'diario','mensal' => [
                'valor_venda'       => ['required','numeric','min:0'],
                'valor_venda_total' => ['nullable','numeric','min:0'],
                'valor_mensalidade' => ['nullable','numeric','min:0'],
            ],
            default => [
                'valor_venda'       => ['nullable','numeric','min:0'],
                'valor_venda_total' => ['nullable','numeric','min:0'],
                'valor_mensalidade' => ['nullable','numeric','min:0'],
            ],
        };

        return array_merge([
            'tipo'         => ['required','in:pf,pj'],
            'dados'        => ['required','array'],            // ⬅️ agora é array
            'cotacao_id'   => ['nullable','integer','exists:cotacoes,id'],
            'tipo_venda'   => ['nullable','in:diario,mensal,assinatura'],
            'documentos'   => ['nullable','array'],
            'documentos.*' => ['file','max:5120'],
        ], $regrasTipo);
    }

    public function messages(): array
    {
        return [
            'dados.array' => 'O campo dados deve conter um array.',
        ];
    }
}
