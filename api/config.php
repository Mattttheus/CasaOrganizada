<?php
// Conexão com o MySQL local (WampServer). Os valores abaixo são o padrão do
// WAMP: MySQL 8 na porta 3306, usuário root sem senha.
//
// Para mudar sem mexer neste arquivo, crie api/config.local.php (ignorado
// pelo git) retornando só as chaves que quiser trocar, por exemplo:
//   <?php return ['pass' => 'minha-senha', 'port' => 3307];
$config = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'casa_organizada',
    'user' => 'root',
    'pass' => '',
    // Projeto invest (mesmo PC) para o resumo de dividendos/proventos do painel.
    'invest_url' => 'http://127.0.0.1:8081',
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_merge($config, require __DIR__ . '/config.local.php');
}

// Testes automatizados (tests/api.test.mjs) apontam para um banco separado por variáveis de
// ambiente do servidor de teste (php -S); pedidos web não conseguem definir estas variáveis.
foreach (['name' => 'CASA_DB_NAME', 'user' => 'CASA_DB_USER', 'pass' => 'CASA_DB_PASS', 'invest_url' => 'CASA_INVEST_URL'] as $chave => $variavel) {
    $valor = getenv($variavel);
    if ($valor !== false) $config[$chave] = $valor;
}

return $config;
