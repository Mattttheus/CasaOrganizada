<?php
declare(strict_types=1);

/**
 * Conexão PDO única do CasaOrganizada.
 *
 * Em produção, configure estes valores como variáveis de ambiente do hosting.
 * O padrão continua sendo MySQL para manter o desenvolvimento local.
 */
const DB_DRIVER = 'mysql';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'gestao_familiar';
const DB_USER = 'root';
const DB_PASS = '4605';
const DB_PORT = '3306';

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

    $driver = strtolower(dbConfig('CASAORGANIZADA_DB_DRIVER', DB_DRIVER));
    $host = dbConfig('CASAORGANIZADA_DB_HOST', DB_HOST);
    $nome = dbConfig('CASAORGANIZADA_DB_NAME', DB_NAME);
    $usuario = dbConfig('CASAORGANIZADA_DB_USER', DB_USER);
    $senha = dbConfig('CASAORGANIZADA_DB_PASS', DB_PASS);
    $porta = dbConfig('CASAORGANIZADA_DB_PORT', DB_PORT);

    if ($driver === 'pgsql') {
        $dsn = 'pgsql:host=' . $host . ';port=' . $porta . ';dbname=' . $nome . ';sslmode=require';
    } elseif ($driver === 'mysql') {
        $dsn = 'mysql:host=' . $host . ';port=' . $porta . ';dbname=' . $nome . ';charset=utf8mb4';
    } else {
        throw new InvalidArgumentException('Driver de banco inválido. Use mysql ou pgsql.');
    }

    $pdo = new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ]);

    return $pdo;
}