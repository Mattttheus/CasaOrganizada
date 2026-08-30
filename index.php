<?php
declare(strict_types=1);

/**
 * Produção: nunca exibir erros/avisos do PHP na tela; tudo vai para o log do servidor.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

function paginaErroGenerica(): void
{
    http_response_code(500);
    echo <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CasaOrganizada - Erro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="alert alert-danger shadow-sm">
    <h4 class="alert-heading">Não foi possível concluir a operação</h4>
    <p class="mb-0">Ocorreu um erro interno. Tente novamente em instantes.</p>
  </div>
  <a href="index.php?route=dashboard" class="btn btn-primary">Voltar ao Dashboard</a>
</div>
</body>
</html>
HTML;
}

// Captura erros fatais (ex.: falha de configuração) que não passam pelo try/catch abaixo.
register_shutdown_function(static function (): void {
    $erro = error_get_last();

    if ($erro !== null && in_array($erro['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        error_log('[CasaOrganizada] Erro fatal: ' . $erro['message'] . ' em ' . $erro['file'] . ':' . $erro['line']);

        if (!headers_sent()) {
            paginaErroGenerica();
        }
    }
});

require_once __DIR__ . '/config/seguranca.php';

iniciarSessaoSegura();
enviarCabecalhosSeguranca();

require_once __DIR__ . '/app/Controllers/FinanceiroController.php';
require_once __DIR__ . '/app/Controllers/MembrosController.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';

$route = $_GET['route'] ?? (AuthController::usuarioLogado() ? 'dashboard' : 'login');

// Rotas acessíveis sem login
$rotasPublicas = ['login', 'login_store'];

if (!in_array($route, $rotasPublicas, true) && !AuthController::usuarioLogado()) {
    header('Location: index.php?route=login');
    exit;
}

// Toda rota que altera dados exige um token CSRF válido.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValido()) {
    http_response_code(403);
    echo 'Requisição inválida ou expirada. Volte e tente novamente.';
    exit;
}

try {
    switch ($route) {
        case 'login':
            (new AuthController())->showLogin();
            break;

        case 'login_store':
            (new AuthController())->login();
            break;

        case 'logout':
            (new AuthController())->logout();
            break;

        case 'dashboard':
            $controller = new FinanceiroController();
            $data = $controller->data();
            extract($data, EXTR_SKIP);
            require __DIR__ . '/views/dashboard.php';
            break;

        case 'receitas':
            $controller = new FinanceiroController();
            $data = $controller->data();
            extract($data, EXTR_SKIP);
            require __DIR__ . '/views/receitas.php';
            break;

        case 'despesas':
            $controller = new FinanceiroController();
            $data = $controller->data();
            extract($data, EXTR_SKIP);
            require __DIR__ . '/views/despesas.php';
            break;

        case 'cartoes':
            $controller = new FinanceiroController();
            $data = $controller->dataCartoes();
            extract($data, EXTR_SKIP);
            require __DIR__ . '/views/cartoes.php';
            break;

        case 'cartao_store':
            (new FinanceiroController())->storeCartao();
            break;

        case 'cartao_update':
            (new FinanceiroController())->updateCartao();
            break;

        case 'cartao_delete':
            (new FinanceiroController())->deleteCartao();
            break;

        case 'parcelamentos':
            $controller = new FinanceiroController();
            $data = $controller->dataParcelamentos();
            extract($data, EXTR_SKIP);
            require __DIR__ . '/views/parcelamentos.php';
            break;

        case 'parcela_pagar':
            (new FinanceiroController())->pagarParcela();
            break;

        case 'parcelamento_delete':
            (new FinanceiroController())->deleteParcelamento();
            break;

        case 'membros':
            $controller = new MembrosController();
            $data = $controller->data();
            extract($data, EXTR_SKIP);
            require __DIR__ . '/views/membros.php';
            break;

        case 'membro_store':
            (new MembrosController())->storeMembro();
            break;

        case 'membro_update':
            (new MembrosController())->updateMembro();
            break;

        case 'membro_delete':
            (new MembrosController())->deleteMembro();
            break;

        case 'usuario_store':
            (new MembrosController())->storeUsuario();
            break;

        case 'usuario_update':
            (new MembrosController())->updateUsuario();
            break;

        case 'usuario_delete':
            (new MembrosController())->deleteUsuario();
            break;

        case 'receitas_store':
            (new FinanceiroController())->storeReceita();
            break;

        case 'receitas_update':
            (new FinanceiroController())->updateReceita();
            break;

        case 'receitas_delete':
            (new FinanceiroController())->deleteReceita();
            break;

        case 'despesas_store':
            (new FinanceiroController())->storeDespesa();
            break;

        case 'parcelamento_store':
            (new FinanceiroController())->storeParcelamento();
            break;

        case 'atualizar_despesa':
            (new FinanceiroController())->updateDespesa();
            break;

        case 'excluir_despesa':
            (new FinanceiroController())->deleteDespesa();
            break;

        case 'salvar_lancamento':
            (new FinanceiroController())->storeLancamento();
            break;

        case 'salvar_anotacao':
            (new FinanceiroController())->storeAnotacao();
            break;

        default:
            http_response_code(404);
            echo 'Rota não encontrada.';
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);

    $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CasaOrganizada - Erro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="alert alert-danger shadow-sm">
    <h4 class="alert-heading">Não foi possível concluir a operação</h4>
    <p class="mb-0">{$message}</p>
  </div>
  <a href="index.php?route=dashboard" class="btn btn-primary">Voltar ao Dashboard</a>
</div>
</body>
</html>
HTML;
} catch (Throwable $e) {
    http_response_code(500);

    // Detalhes técnicos vão para o log; o usuário só vê uma mensagem genérica.
    error_log('[CasaOrganizada] ' . $e->getMessage());

    echo <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CasaOrganizada - Erro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="alert alert-danger shadow-sm">
    <h4 class="alert-heading">Não foi possível concluir a operação</h4>
    <p class="mb-0">Ocorreu um erro interno. Tente novamente em instantes.</p>
  </div>
  <a href="index.php?route=dashboard" class="btn btn-primary">Voltar ao Dashboard</a>
</div>
</body>
</html>
HTML;
}