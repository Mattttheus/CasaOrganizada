<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/app/Controllers/FinanceiroController.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';

$route = $_GET['route'] ?? 'dashboard';

// Rotas acessíveis sem login
$rotasPublicas = ['login', 'login_store'];

if (!in_array($route, $rotasPublicas, true) && !AuthController::usuarioLogado()) {
    header('Location: index.php?route=login');
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
} catch (Throwable $e) {
    http_response_code(500);

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
}