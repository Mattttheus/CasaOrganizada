<?php
// Conexão PDO com o MySQL (config.php / config.local.php) e geração de IDs.
defined('CASA_API') || exit;

function configuracao(): array
{
    static $config = null;
    return $config ??= require dirname(__DIR__) . '/config.php';
}

function conexao(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = configuracao();
    try {
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
    } catch (PDOException $e) {
        error_log('CasaOrganizada: sem conexão com o MySQL: ' . $e->getMessage());
        falhar('Não foi possível conectar ao banco de dados. Confira se o MySQL está rodando e se database/mysql.sql foi importado.', 500);
    }
    return $pdo;
}

/** Executa um SQL com parâmetros e devolve o statement. */
function sql(string $consulta, array $parametros = []): PDOStatement
{
    $stmt = conexao()->prepare($consulta);
    $stmt->execute($parametros);
    return $stmt;
}

/** UUID v4 (mesmo formato dos IDs do Supabase). */
function uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}
