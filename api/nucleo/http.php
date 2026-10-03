<?php
// Respostas JSON, leitura do corpo e proteção contra pedidos de outros sites.
defined('CASA_API') || exit;

function cabecalhosApi(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    header('Referrer-Policy: same-origin');
    header_remove('X-Powered-By');
}

/** Encerra o pedido com o JSON dado. */
function responder($dados, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Encerra com {"error": mensagem} — o front-end mostra a mensagem como veio. */
function falhar(string $mensagem, int $status = 400): void
{
    responder(['error' => $mensagem], $status);
}

/**
 * Corpo JSON de um POST. Exigir application/json impede formulários de outros sites
 * (navegador faria preflight CORS, que esta API não libera).
 */
function corpo(): array
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) falhar('Content-Type deve ser application/json.', 415);
    $dados = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($dados) ? $dados : [];
}

/** Se o navegador informar a origem, ela tem de ser este mesmo endereço (defesa extra contra CSRF). */
function exigirMesmaOrigem(): void
{
    $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origem === '') return;
    $host = parse_url($origem, PHP_URL_HOST) . ':' . (parse_url($origem, PHP_URL_PORT) ?: 80);
    $aqui = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '') . ':' . ($_SERVER['SERVER_PORT'] ?? 80);
    if (strcasecmp($host, $aqui) !== 0) falhar('Origem não permitida.', 403);
}
