<?php
// Limites contra tentativa e erro (senha errada, troca de senha) por IP e por conta.
defined('CASA_API') || exit;

const LIMITE_JANELA = 15 * 60;   // segundos
const LIMITE_POR_EMAIL = 5;      // senhas erradas por IP + e-mail
const LIMITE_POR_IP = 20;        // senhas erradas por IP (qualquer e-mail)
const LIMITE_SENHA_ATUAL = 5;    // "senha atual" errada ao trocar a própria senha

function ipCliente(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * IP "de casa": este PC, a rede local ou a VPN Tailscale (100.64.0.0/10, fd7a:115c:a1e0::/48).
 * Qualquer outro chegou pela internet (porta aberta no roteador).
 */
function ipDeCasa(string $ip): bool
{
    if ($ip === '::1' || str_starts_with($ip, '127.') || str_starts_with(strtolower($ip), 'fe80:') || str_starts_with(strtolower($ip), 'fd7a:115c:a1e0:')) return true;
    $n = ip2long($ip);
    if ($n === false) return false;
    foreach ([['192.168.0.0', 16], ['10.0.0.0', 8], ['172.16.0.0', 12], ['100.64.0.0', 10]] as [$rede, $bits]) {
        $mascara = -1 << (32 - $bits);
        if (($n & $mascara) === (ip2long($rede) & $mascara)) return true;
    }
    return false;
}

function contarTentativas(string $chave, int $janela = LIMITE_JANELA): int
{
    return (int)sql('SELECT COUNT(*) FROM tentativas_login WHERE chave = ? AND momento > NOW() - INTERVAL ? SECOND', [$chave, $janela])->fetchColumn();
}

function registrarTentativa(string $chave): void
{
    sql('INSERT INTO tentativas_login (chave) VALUES (?)', [$chave]);
    conexao()->exec('DELETE FROM tentativas_login WHERE momento < NOW() - INTERVAL 1 DAY');
}

function limparTentativas(string $chave): void
{
    sql('DELETE FROM tentativas_login WHERE chave = ?', [$chave]);
}

/** Falha com 429 se a chave passou do limite na janela. */
function exigirAbaixoDoLimite(string $chave, int $limite, string $mensagem): void
{
    if (contarTentativas($chave) >= $limite) falhar($mensagem, 429);
}
