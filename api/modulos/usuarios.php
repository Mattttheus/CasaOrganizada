<?php
// Contas de acesso (página Família). Admin cadastra, edita e exclui; os demais só editam a
// própria conta (nome, e-mail e senha, com a senha atual). O sistema nunca fica sem admin.
defined('CASA_API') || exit;

function totalAdmins(): int
{
    return (int)conexao()->query('SELECT COUNT(*) FROM usuarios WHERE admin = 1')->fetchColumn();
}

function contaPorId(string $id): array
{
    $u = sql('SELECT * FROM usuarios WHERE id = ?', [uuidValido($id, 'Conta')])->fetch();
    if (!$u) falhar('Conta não encontrada.', 404);
    return $u;
}

function exigirEmailLivre(string $email, string $exceto = ''): void
{
    if (sql('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?', [$email, $exceto])->fetch()) falhar('Já existe uma conta com este e-mail.', 409);
}

/** GET usuarios -> admin vê todas as contas; os demais, só a própria. */
function acaoUsuarios(): void
{
    $eu = contaAtual();
    $linhas = $eu['admin']
        ? conexao()->query('SELECT id, nome, email, acesso_invest, admin, criado_em FROM usuarios ORDER BY nome')->fetchAll()
        : sql('SELECT id, nome, email, acesso_invest, admin, criado_em FROM usuarios WHERE id = ?', [$eu['id']])->fetchAll();
    responder(array_map(fn($u) => usuarioPublico($u) + ['criado_em' => $u['criado_em']], $linhas));
}

/** POST usuario_salvar {id?, nome, email, senha?, senha_atual?, invest?, admin?} */
function acaoUsuarioSalvar(): void
{
    $eu = contaAtual();
    $body = corpo();
    $id = (string)($body['id'] ?? '');
    $nome = nomeValido($body['nome'] ?? '');
    $email = emailValido($body['email'] ?? '');
    $senha = (string)($body['senha'] ?? '');
    if ($id === '') criarConta($nome, $email, $senha, $body);
    editarConta($eu, contaPorId($id), $nome, $email, $senha, $body);
}

function criarConta(string $nome, string $email, string $senha, array $body): void
{
    exigirAdmin();
    exigirEmailLivre($email);
    sql('INSERT INTO usuarios (id, nome, email, senha, acesso_invest, admin) VALUES (?, ?, ?, ?, ?, ?)',
        [uuid(), $nome, $email, password_hash(senhaValida($senha), PASSWORD_DEFAULT), empty($body['invest']) ? 0 : 1, empty($body['admin']) ? 0 : 1]);
    responder(['ok' => true, 'resumo' => "Acesso criado para $nome."], 201);
}

function editarConta(array $eu, array $alvo, string $nome, string $email, string $senha, array $body): void
{
    $propria = $alvo['id'] === $eu['id'];
    if (!$propria && !$eu['admin']) falhar('Só administradores podem editar outras contas.', 403);
    exigirEmailLivre($email, $alvo['id']);
    $campos = ['nome' => $nome, 'email' => $email];
    if ($eu['admin']) {   // permissões: só o admin muda (quem não é admin não se promove)
        $campos['acesso_invest'] = empty($body['invest']) ? 0 : 1;
        $campos['admin'] = empty($body['admin']) ? 0 : 1;
        if ($alvo['admin'] && !$campos['admin'] && totalAdmins() <= 1) falhar('O sistema precisa de pelo menos um administrador.', 422);
    }
    if ($senha !== '') {
        if ($propria) conferirSenhaAtual($alvo, (string)($body['senha_atual'] ?? ''));
        $campos['senha'] = password_hash(senhaValida($senha), PASSWORD_DEFAULT);
    }
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
    sql("UPDATE usuarios SET $sets WHERE id = ?", [...array_values($campos), $alvo['id']]);
    if ($senha !== '' && !$propria) sql('DELETE FROM tentativas_login WHERE chave LIKE ?', ['%|' . $email]);   // desbloqueia o login
    responder(['ok' => true, 'resumo' => "Conta de $nome atualizada."]);
}

/** Trocar a própria senha exige a atual, com limite de tentativas. */
function conferirSenhaAtual(array $conta, string $senhaAtual): void
{
    $chave = 'senha|' . $conta['id'];
    exigirAbaixoDoLimite($chave, LIMITE_SENHA_ATUAL, 'Muitas tentativas com a senha atual errada. Aguarde 15 minutos.');
    if (!password_verify($senhaAtual, $conta['senha'])) {
        registrarTentativa($chave);
        falhar('Senha atual incorreta.', 422);
    }
    limparTentativas($chave);
}

/** POST usuario_excluir {id} — só admin, nunca a própria conta nem o último admin. */
function acaoUsuarioExcluir(): void
{
    $eu = exigirAdmin();
    $alvo = contaPorId((string)(corpo()['id'] ?? ''));
    if ($alvo['id'] === $eu['id']) falhar('Você não pode excluir a própria conta.', 422);
    if ($alvo['admin'] && totalAdmins() <= 1) falhar('O sistema precisa de pelo menos um administrador.', 422);
    sql('DELETE FROM usuarios WHERE id = ?', [$alvo['id']]);
    responder(['ok' => true, 'resumo' => "Acesso de {$alvo['nome']} excluído."]);
}
