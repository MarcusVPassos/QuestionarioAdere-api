<?php

namespace App\Services;

use App\Models\Cotacao;
use App\Models\Questionario;

class SyncCotacaoStatusService
{
    /**
     * Sincroniza a cotação a partir do status do questionário, de forma idempotente.
     * Regras:
     * - pendente  -> em_atendimento + "Aguardando aprovação do questionário"
     * - negado    -> recusado      + "Negado — Questionário recusado"
     * - aprovado  -> convertido    + "Aprovado via questionário"
     */
    public static function syncFromQuestionario(Questionario $q): void
    {
        if (!$q->cotacao_id) return;

        /** @var Cotacao $c */
        $c = Cotacao::find($q->cotacao_id);
        if (!$c) return;

        $alvoStatus = $c->status;
        $alvoDetalhe = $c->status_detalhe;
        $alvoMotivo = $c->motivo_recusa;

        switch ($q->status) {
            case 'pendente':
                $alvoStatus  = 'em_atendimento';
                $alvoDetalhe = 'Aguardando aprovação';
                $alvoMotivo  = null;
                break;

            case 'correcao':
                $alvoStatus = 'negociacao';
                $alvoDetalhe = 'Pendencia de Dados';
                $alvoMotivo = null;
                break;

            case 'negado':
                // No domínio da Cotação não há "negado", há "recusado"
                $alvoStatus  = 'recusado';
                $alvoDetalhe = 'Questionário recusado';
                // manter motivo explícito se veio do questionário
                $alvoMotivo  = $q->motivo_negativa ?: $alvoMotivo;
                break;

            case 'aprovado':
                $alvoStatus  = 'convertido';
                $alvoDetalhe = 'Cotação convertida em venda';
                $alvoMotivo  = null;
                break;

            // 'correcao' não altera a cotação
            default:
                return;
        }

        // ✅ Idempotência: só salva se algo mudou
        if ($c->status !== $alvoStatus || $c->status_detalhe !== $alvoDetalhe || $c->motivo_recusa !== $alvoMotivo) {
            $c->status = $alvoStatus;
            $c->status_detalhe = $alvoDetalhe;
            $c->motivo_recusa = $alvoMotivo;
            $c->save();

            // (opcional) emitir evento existente de cotação para realtime
            event(new \App\Events\CotacaoAtualizada($c));
        }
    }
}
