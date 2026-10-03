<?php
// Sessão de login (compartilhada com o Projeto invest: mesmo PC, o cookie vale para
// todas as portas do host) e verificação de permissões.
defined('CASA_API') || exit;

const SESSAO_OCIOSA = 8 * 3600;    // sem uso por 8 h -> pede login de novo
const SESSAO_MAXIMA = 24 * 3600;   // no máximo 24 h desde o login

function iniciarSessao(): void
{
    ini_set('session.use_strict_mode', '1');   // recusa IDs de sessão inventados (fixação de sessão)
    ini_set('session.use_only_cookies', '1');
    ini_set('session.sid_length', '48');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'path' => '/']);
    session_name('casaorganizada');
    session_start();
    $agora = time();
    $expirou = (isset($_SESSION['visto']) && $agora - $_SESSION['visto'] > SESSAO_OCIOSA)
        || (isset($_SESSION['inicio']) && $agora - $_SESSION['inicio'] > SESSAO_MAXIMA);
    if ($expirou) $_SESSION = [];
    $_SESSION['visto'] = $agora;
}

/** Abre a sessão do usuário (novo ID de sessão a cada login). */
function abrirSessao(array $u): array
{
    session_regenerate_id(true);
    $_SESSION['inicio'] = time();
    return $_SESSION['user'] = usuarioPublico($u);
}

function encerrarSessao(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

/** Dados do usuário que podem ir para o navegador (nunca a senha). */
function usuarioPublico(array $u): array
{
    return ['id' => $u['id'], 'nome' => $u['nome'], 'email' => $u['email'],
        'invest' => (bool)($u['acesso_invest'] ?? false), 'admin' => (bool)($u['admin'] ?? false)];
}

function usuarioLogado(): array
{
    if (empty($_SESSION['user'])) falhar('Sessão expirada. Entre novamente.', 401);
    return $_SESSION['user'];
}

/** Conta atual relida do banco: permissão retirada ou conta excluída valem na hora. */
function contaAtual(): array
{
    $u = sql('SELECT id, nome, email, acesso_invest, admin FROM usuarios WHERE id = ?', [usuarioLogado()['id']])->fetch();
    if (!$u) {
        encerrarSessao();
        falhar('Sessão expirada. Entre novamente.', 401);
    }
    $_SESSION['user'] = usuarioPublico($u);
    return $u;
}

function exigirAdmin(): array
{
    $u = contaAtual();
    if (!$u['admin']) falhar('Só administradores podem gerenciar os acessos.', 403);
    return $u;
}

function exigirInvest(): array
{
    $u = contaAtual();
    if (!$u['acesso_invest']) falhar('Sua conta não tem acesso aos investimentos.', 403);
    return $u;
}
