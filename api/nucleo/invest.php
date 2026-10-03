<?php
// Ponte com o Projeto invest (mesmo PC, porta 8081): chamada pelo servidor repassando o
// cookie de sessão — o invest valida o login perguntando a esta API (acao=sessao).
defined('CASA_API') || exit;

function chamarInvest(string $caminho, ?array $corpo = null, int $timeout = 10): array
{
    // Libera a sessão antes: o invest consulta acao=sessao com o mesmo cookie e
    // ficaria esperando o arquivo de sessão, travado por este pedido.
    $sid = session_id();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $http = ['header' => 'Cookie: ' . session_name() . '=' . $sid . "\r\n", 'timeout' => $timeout, 'ignore_errors' => true];
    if ($corpo !== null) {
        $http['method'] = 'POST';
        $http['header'] .= "Content-Type: application/json\r\n";
        $http['content'] = json_encode($corpo, JSON_UNESCAPED_UNICODE);
    }
    $url = rtrim(configuracao()['invest_url'], '/') . '/' . $caminho;
    $resposta = @file_get_contents($url, false, stream_context_create(['http' => $http]));
    $json = $resposta === false ? null : json_decode($resposta, true);
    if (!is_array($json)) falhar('O Projeto invest (porta 8081) não respondeu.', 502);
    if (isset($json['erro'])) falhar('Projeto invest: ' . $json['erro'], 502);
    return $json;
}
