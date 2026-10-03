<?php
// Cria (ou recupera) uma conta de administrador pela linha de comando — é assim que se entra pela
// primeira vez, já que não há cadastro aberto. Se o e-mail já existir, a conta vira admin e recebe
// a nova senha (serve para recuperar o acesso). Nunca roda pela web (a pasta database/ também é
// bloqueada no Apache).
//
//   php database/criar_admin.php "Seu nome" seu@email.com "senha-com-10+-caracteres"
//
// Usa a mesma conexão da API (api/config.php + api/config.local.php).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[, $nome, $email, $senha] = array_pad($argv, 4, '');
$nome = trim($nome);
$email = strtolower(trim($email));
$erros = [];
if ($nome === '' || mb_strlen($nome) > 100) $erros[] = 'informe o nome (até 100 caracteres)';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'informe um e-mail válido';
if (strlen($senha) < 10 || !preg_match('/[A-Za-z]/', $senha) || !preg_match('/[0-9]/', $senha)) $erros[] = 'a senha precisa ter 10+ caracteres, com letras e números';
if ($erros) {
    fwrite(STDERR, "Não criado: " . implode('; ', $erros) . ".\nUso: php database/criar_admin.php \"Seu nome\" seu@email.com \"senha-com-10+-caracteres\"\n");
    exit(1);
}

$c = require __DIR__ . '/../api/config.php';
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$hash = password_hash($senha, PASSWORD_DEFAULT);
$existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$existe->execute([$email]);
if ($id = $existe->fetchColumn()) {
    $pdo->prepare('UPDATE usuarios SET nome = ?, senha = ?, admin = 1 WHERE id = ?')->execute([$nome, $hash, $id]);
    $pdo->prepare('DELETE FROM tentativas_login WHERE chave LIKE ?')->execute(['%|' . $email]);
    echo "Conta $email agora é administradora, com a nova senha.\n";
} else {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    $pdo->prepare('INSERT INTO usuarios (id, nome, email, senha, acesso_invest, admin) VALUES (?, ?, ?, ?, 0, 1)')->execute([$id, $nome, $email, $hash]);
    echo "Administrador $email criado. Entre no app e cadastre a família em Família → Acessos ao sistema.\n";
}
