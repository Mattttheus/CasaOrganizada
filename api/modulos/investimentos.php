<?php
// Resumo do Projeto invest para o painel e os objetivos (renda, proventos, posição por ativo).
defined('CASA_API') || exit;

function acaoInvestResumo(): void
{
    exigirInvest();
    responder(chamarInvest('resumo.php'));
}
