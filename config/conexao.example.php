<?php
declare(strict_types=1);

/**
 * MODELO de conexão — copie este arquivo para "conexao.php" no servidor
 * de produção (o conexao.php real não é versionado no git) e preencha
 * com os dados do banco fornecidos pelo hosting (WapServerOnline, etc.).
 */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'nome_do_banco';
const DB_USER = 'usuario_do_banco';
const DB_PASS = 'senha_do_banco';

function dbConfig(string $chave, string $padrao): string
{
    $valor = getenv($chave);

    return ($valor === false || $valor === '') ? $padrao : $valor;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = dbConfig('CASAORGANIZADA_DB_HOST', DB_HOST);
    $nome = dbConfig('CASAORGANIZADA_DB_NAME', DB_NAME);
    $usuario = dbConfig('CASAORGANIZADA_DB_USER', DB_USER);
    $senha = dbConfig('CASAORGANIZADA_DB_PASS', DB_PASS);

    $dsn = 'mysql:host=' . $host . ';dbname=' . $nome . ';charset=utf8mb4';

    $pdo = new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ]);

    return $pdo;
}
