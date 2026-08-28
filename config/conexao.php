<?php
declare(strict_types=1);

/**
 * Conexão PDO única do CasaOrganizada.
 * Ajuste apenas estas quatro constantes para o seu WAMP/XAMPP.
 */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'gestao_familiar';
const DB_USER = 'root';
const DB_PASS = '4605';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

 $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
]);

    return $pdo;
}