<?php
declare(strict_types=1);

/**
 * Conexão PDO única do CasaOrganizada.
 *
 * Em produção, prefira configurar estes valores como variáveis de ambiente
 * do hosting (quando suportado). Caso contrário, edite os valores padrão
 * abaixo diretamente neste arquivo no servidor (ele NÃO é versionado no git).
 */
const DB_DRIVER = 'mysql';
const DB_HOST = '127.0.0.1';
const DB_PORT = '';
const DB_NAME = 'gestao_familiar';
const DB_USER = 'root';
const DB_PASS = '4605';
const DB_SSL_MODE = '';

function dbConfig(string $chave, string $padrao): string
{
    $valor = getenv($chave);

    return ($valor === false || $valor === '') ? $padrao : $valor;
}

function dbDriver(): string
{
    $driver = strtolower(trim(dbConfig('CASAORGANIZADA_DB_DRIVER', DB_DRIVER)));

    return $driver === '' ? 'mysql' : $driver;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = dbDriver();
    $host = dbConfig('CASAORGANIZADA_DB_HOST', DB_HOST);
    $porta = trim(dbConfig('CASAORGANIZADA_DB_PORT', DB_PORT));
    $nome = dbConfig('CASAORGANIZADA_DB_NAME', DB_NAME);
    $usuario = dbConfig('CASAORGANIZADA_DB_USER', DB_USER);
    $senha = dbConfig('CASAORGANIZADA_DB_PASS', DB_PASS);

    if ($driver === 'pgsql') {
        $partes = ['host=' . $host];

        if ($porta !== '') {
            $partes[] = 'port=' . $porta;
        }

        $partes[] = 'dbname=' . $nome;

        $sslMode = trim(dbConfig('CASAORGANIZADA_DB_SSL_MODE', DB_SSL_MODE));
        if ($sslMode !== '') {
            $partes[] = 'sslmode=' . $sslMode;
        }

        $dsn = 'pgsql:' . implode(';', $partes);
    } elseif ($driver === 'mysql') {
        $dsn = 'mysql:host=' . $host;

        if ($porta !== '') {
            $dsn .= ';port=' . $porta;
        }

        $dsn .= ';dbname=' . $nome . ';charset=utf8mb4';
    } else {
        throw new InvalidArgumentException('Driver de banco invÃ¡lido. Use "mysql" ou "pgsql".');
    }

    $pdo = new PDO($dsn, $usuario, $senha, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ]);

    return $pdo;
}