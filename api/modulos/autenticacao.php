<?php
// Login, sessão e saída. Novas contas só são criadas pelo administrador (módulo usuarios).
defined('CASA_API') || exit;

// Hash de uma senha aleatória descartada: e-mail inexistente custa o mesmo tempo que senha errada.
const HASH_FALSO = '$2y$10$jL9mfjdROdQvoMOPrbxS8uISk5i7rZ7C7s26el0XXct4WrYuuCBUS';

/** GET sessao -> {user} (também usado pelo Projeto invest para validar o login). */
function acaoSessao(): void
{
    if (empty($_SESSION['user'])) responder(['user' => null]);
    $u = sql('SELECT id, nome, email, acesso_invest, admin FROM usuarios WHERE id = ?', [$_SESSION['user']['id']])->fetch();
    if (!$u) {
        encerrarSessao();
        responder(['user' => null]);
    }
    responder(['user' => $_SESSION['user'] = usuarioPublico($u)]);
}

/**
 * POST login {email, senha}. Mensagem única para e-mail inexistente e senha errada (não revela
 * quais e-mails têm conta) e mesmo custo de tempo nos dois casos.
 */
function acaoLogin(): void
{
    $body = corpo();
    $email = trim(strtolower((string)($body['email'] ?? '')));
    $senha = (string)($body['senha'] ?? '');
    $ip = ipCliente();
    $chave = "login|$ip|$email";
    $bloqueado = 'Muitas tentativas de login. Aguarde 15 minutos e tente de novo.';
    exigirAbaixoDoLimite($chave, LIMITE_POR_EMAIL, $bloqueado);
    exigirAbaixoDoLimite("ip|$ip", LIMITE_POR_IP, $bloqueado);

    $u = sql('SELECT id, nome, email, senha, acesso_invest, admin FROM usuarios WHERE email = ?', [$email])->fetch();
    $hash = $u ? $u['senha'] : HASH_FALSO;
    if (!password_verify($senha, $hash) || !$u) {
        registrarTentativa($chave);
        registrarTentativa("ip|$ip");
        falhar('E-mail ou senha inválidos.', 401);
    }
    limparTentativas($chave);
    if (password_needs_rehash($u['senha'], PASSWORD_DEFAULT)) {
        sql('UPDATE usuarios SET senha = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
    }
    responder(['user' => abrirSessao($u)]);
}

/** POST cadastro: desligado — novos acessos são criados pelo administrador na página Família. */
function acaoCadastro(): void
{
    falhar('Novos acessos são criados pelo administrador na página Família.', 403);
}

function acaoSair(): void
{
    encerrarSessao();
    responder(['ok' => true]);
}
