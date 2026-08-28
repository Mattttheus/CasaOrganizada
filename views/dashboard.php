<?php

/**
 * ============================================================
 * CASA ORGANIZADA
 * DASHBOARD FINANCEIRO
 * ============================================================
 */

// Inicia sessão caso ainda não esteja iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ============================================================
 * PROTEÇÃO DE AUTENTICAÇÃO
 * ============================================================
 *
 * Aceita diferentes nomes de sessão para facilitar
 * a integração com o login existente.
 */

$usuarioId = $_SESSION['usuario_id']
    ?? $_SESSION['user_id']
    ?? null;

if (empty($usuarioId)) {
    header('Location: index.php?route=login');
    exit;
}

/**
 * ============================================================
 * DADOS DO USUÁRIO
 * ============================================================
 */

$usuarioNome = $_SESSION['nome']
    ?? $_SESSION['usuario']
    ?? $_SESSION['usuario_nome']
    ?? 'Usuário';

$usuarioEmail = $_SESSION['email'] ?? '';

/**
 * ============================================================
 * PERÍODO SELECIONADO
 * ============================================================
 */

$month = (int)($month ?? $_GET['mes'] ?? date('n'));
$year  = (int)($year ?? $_GET['ano'] ?? date('Y'));

// Validação do mês
if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}

// Validação básica do ano
if ($year < 2020 || $year > 2100) {
    $year = (int)date('Y');
}

/**
 * ============================================================
 * NOMES DOS MESES
 * ============================================================
 */

$nomesMeses = [
    1  => 'Janeiro',
    2  => 'Fevereiro',
    3  => 'Março',
    4  => 'Abril',
    5  => 'Maio',
    6  => 'Junho',
    7  => 'Julho',
    8  => 'Agosto',
    9  => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro'
];

// =====================================================
// INICIALIZAÇÃO SEGURA DAS VARIÁVEIS DO DASHBOARD
// =====================================================
// Mês e ano
$month = isset($month) ? (int)$month : (int)date('n');
$year  = isset($year) ? (int)$year : (int)date('Y');

// Arrays principais
if (!isset($receitas) || !is_array($receitas)) {
    $receitas = [];
}

if (!isset($despesas) || !is_array($despesas)) {
    $despesas = [];
}

if (!isset($anotacoes) || !is_array($anotacoes)) {
    $anotacoes = [];
}

// Dados para gráficos
if (!isset($despesasCategorias) || !is_array($despesasCategorias)) {
    $despesasCategorias = [];
}

if (!isset($receitasCategorias) || !is_array($receitasCategorias)) {
    $receitasCategorias = [];
}

?>

/**
* ============================================================
* FUNÇÕES AUXILIARES
* ============================================================
*/

$esc = static function ($valor): string {
return htmlspecialchars(
(string)($valor ?? ''),
ENT_QUOTES,
'UTF-8'
);
};

$money = static function ($valor): string {
return 'R$ ' . number_format(
(float)($valor ?? 0),
2,
',',
'.'
);
};

/**
* ============================================================
* TOTAL DE RECEITAS
* ============================================================
*/

$totalReceitas = isset($totalReceitas)
? (float)$totalReceitas
: array_sum(
array_map(
static function ($receita): float {

return (float)(
$receita['valor_real']
?? $receita['valor_recebido']
?? $receita['valor']
?? 0
);

},
$receitas
)
);

/**
* ============================================================
* TOTAL DE DESPESAS REALIZADAS
* ============================================================
*/

$totalDespesasRealizadas = isset($totalDespesasRealizadas)
? (float)$totalDespesasRealizadas
: array_sum(
array_map(
static function ($despesa): float {

return (float)(
$despesa['valor_real']
?? $despesa['valor_realizado']
?? $despesa['valor']
?? 0
);

},
$despesas
)
);

/**
* ============================================================
* TOTAL DE DESPESAS PREVISTAS
* ============================================================
*/

$totalDespesasPrevistas = isset($totalDespesasPrevistas)
? (float)$totalDespesasPrevistas
: array_sum(
array_map(
static function ($despesa): float {

return (float)(
$despesa['valor_previsto']
?? $despesa['valor_real']
?? $despesa['valor_realizado']
?? $despesa['valor']
?? 0
);

},
$despesas
)
);

/**
* ============================================================
* SALDOS
* ============================================================
*/

$saldoReal = $totalReceitas - $totalDespesasRealizadas;

$saldoPrevisto = $totalReceitas - $totalDespesasPrevistas;

/**
* ============================================================
* AGRUPAMENTO DE DESPESAS POR CATEGORIA
* ============================================================
*/

if (
!isset($despesasCategorias)
|| !is_array($despesasCategorias)
) {

$despesasCategorias = [];

foreach ($despesas as $despesa) {

$categoria = trim(
(string)(
$despesa['categoria']
?? 'Outros'
)
);

if ($categoria === '') {
$categoria = 'Outros';
}

$valor = (float)(
$despesa['valor_real']
?? $despesa['valor_realizado']
?? $despesa['valor']
?? 0
);

$despesasCategorias[$categoria] =
($despesasCategorias[$categoria] ?? 0)
+ $valor;
}

arsort($despesasCategorias);
}

/**
* ============================================================
* AGRUPAMENTO DE RECEITAS POR CATEGORIA
* ============================================================
*/

if (
!isset($receitasCategorias)
|| !is_array($receitasCategorias)
) {

$receitasCategorias = [];

foreach ($receitas as $receita) {

$categoria = trim(
(string)(
$receita['categoria']
?? 'Outros'
)
);

if ($categoria === '') {
$categoria = 'Outros';
}

$valor = (float)(
$receita['valor_real']
?? $receita['valor_recebido']
?? $receita['valor']
?? 0
);

$receitasCategorias[$categoria] =
($receitasCategorias[$categoria] ?? 0)
+ $valor;
}

arsort($receitasCategorias);
}

/**
* ============================================================
* DADOS PARA OS GRÁFICOS
* ============================================================
*/

$jsonFlags =
JSON_UNESCAPED_UNICODE
| JSON_HEX_TAG
| JSON_HEX_AMP
| JSON_HEX_APOS
| JSON_HEX_QUOT;

$chartDespLabels = json_encode(
array_keys($despesasCategorias),
$jsonFlags
);

$chartDespData = json_encode(
array_values($despesasCategorias),
$jsonFlags
);

$chartRecLabels = json_encode(
array_keys($receitasCategorias),
$jsonFlags
);

$chartRecData = json_encode(
array_values($receitasCategorias),
$jsonFlags
);

/**
* ============================================================
* DATAS DAS ANOTAÇÕES
* ============================================================
*/

$agendaDates = [];

foreach ($anotacoes as $anotacao) {

if (!empty($anotacao['data_agendamento'])) {

$agendaDates[] =
$anotacao['data_agendamento'];
}
}

$agendaDates = array_values(
array_unique($agendaDates)
);

?>
<!doctype html>

<html lang="pt-BR">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>
        CasaOrganizada | Dashboard
    </title>

    <!-- Google Fonts -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <!-- Chart.js -->

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- CSS do sistema -->

    <style>
    :root {

        --co-navy: #172033;

        --co-blue: #2563eb;

        --co-green: #16a34a;

        --co-red: #dc2626;

        --co-text: #1e293b;

        --co-muted: #64748b;

        --co-border: #e2e8f0;

        --co-bg: #f8fafc;

        --co-card: #ffffff;

        --co-radius: 12px;

        --co-shadow:
            0 4px 16px rgba(15, 23, 42, .04);

    }


    * {
        box-sizing: border-box;
    }


    body {

        background-color:
            var(--co-bg);

        color:
            var(--co-text);

        font-family:
            'Inter',
            system-ui,
            -apple-system,
            sans-serif;

        font-size:
            0.875rem;

    }


    /* =====================================================
           NAVBAR
        ===================================================== */

    .navbar-corporate {

        background:
            var(--co-navy);

        min-height:
            64px;

        box-shadow:
            0 2px 12px rgba(15, 23, 42, .12);

    }


    .brand-mark {

        width:
            38px;

        height:
            38px;

        display:
            inline-flex;

        align-items:
            center;

        justify-content:
            center;

        border-radius:
            10px;

        background:
            var(--co-blue);

    }


    /* =====================================================
           PÁGINA
        ===================================================== */

    .page {

        max-width:
            1550px;

        margin:
            0 auto;

        padding:
            24px;

    }


    /* =====================================================
           CARDS
        ===================================================== */

    .cardx {

        background:
            var(--co-card);

        border:
            1px solid var(--co-border);

        border-radius:
            var(--co-radius);

        box-shadow:
            var(--co-shadow);

    }


    .headerx {

        padding:
            16px 20px;

        background:
            #ffffff;

        border-bottom:
            1px solid var(--co-border);

        display:
            flex;

        align-items:
            center;

        justify-content:
            space-between;

        border-top-left-radius:
            var(--co-radius);

        border-top-right-radius:
            var(--co-radius);

    }


    .bodyx {

        padding:
            20px;

    }


    /* =====================================================
           MÉTRICAS
        ===================================================== */

    .metric {

        padding:
            20px;

        transition:
            transform .2s ease,
            box-shadow .2s ease;

    }


    .metric:hover {

        transform:
            translateY(-3px);

        box-shadow:
            0 10px 25px rgba(15, 23, 42, .08);

    }


    .metric-label {

        font-size:
            .75rem;

        font-weight:
            700;

        text-transform:
            uppercase;

        letter-spacing:
            .05em;

        color:
            var(--co-muted);

        margin-bottom:
            6px;

    }


    .metric-value {

        font-size:
            1.4rem;

        font-weight:
            800;

    }


    .iconx {

        width:
            46px;

        height:
            46px;

        border-radius:
            12px;

        display:
            flex;

        align-items:
            center;

        justify-content:
            center;

        font-size:
            1.1rem;

    }


    .soft-green {

        background:
            #f0fdf4;

        color:
            var(--co-green);

    }


    .soft-red {

        background:
            #fef2f2;

        color:
            var(--co-red);

    }


    .soft-blue {

        background:
            #eff6ff;

        color:
            var(--co-blue);

    }


    /* =====================================================
           GRÁFICOS
        ===================================================== */

    .chart {

        position:
            relative;

        height:
            300px;

    }


    .chart-small {

        position:
            relative;

        height:
            300px;

    }


    /* =====================================================
           TABELAS
        ===================================================== */

    .table th {

        background:
            #f8fafc;

        color:
            var(--co-muted);

        font-size:
            .72rem;

        text-transform:
            uppercase;

        letter-spacing:
            .04em;

        font-weight:
            700;

        border-bottom:
            1px solid var(--co-border);

        white-space:
            nowrap;

    }


    .table td {

        vertical-align:
            middle;

        padding:
            12px 16px;

        border-color:
            #f1f5f9;

    }


    /* =====================================================
           CALENDÁRIO
        ===================================================== */

    .calendar-week {

        display:
            grid;

        grid-template-columns:
            repeat(7, 1fr);

        gap:
            2px;

        text-align:
            center;

        font-weight:
            700;

        font-size:
            .72rem;

        color:
            var(--co-muted);

        margin-bottom:
            8px;

    }


    .calendar-grid {

        display:
            grid;

        grid-template-columns:
            repeat(7, 1fr);

        gap:
            4px;

    }


    .calendar-day {

        aspect-ratio:
            1;

        border:
            1px solid transparent;

        background:
            #f8fafc;

        border-radius:
            8px;

        font-size:
            .8rem;

        font-weight:
            600;

        color:
            var(--co-text);

        display:
            flex;

        align-items:
            center;

        justify-content:
            center;

        cursor:
            pointer;

        position:
            relative;

        transition:
            all .15s ease;

    }


    .calendar-day:hover {

        background:
            #e2e8f0;

    }


    .calendar-day.today {

        border-color:
            var(--co-blue);

        color:
            var(--co-blue);

        font-weight:
            800;

    }


    .calendar-day.selected {

        background:
            var(--co-blue);

        color:
            #ffffff;

    }


    .calendar-day.has-note::after {

        content:
            '';

        position:
            absolute;

        bottom:
            4px;

        width:
            5px;

        height:
            5px;

        border-radius:
            50%;

        background:
            var(--co-red);

    }


    .calendar-day.selected.has-note::after {

        background:
            #ffffff;

    }


    /* =====================================================
           RESPONSIVIDADE
        ===================================================== */

    @media (max-width: 768px) {

        .page {

            padding:
                15px;

        }


        .metric-value {

            font-size:
                1.05rem;

        }


        .headerx {

            padding:
                14px;

        }

    }
    </style>

</head>


<body>


    <!-- =========================================================
     NAVBAR
========================================================= -->

    <nav class="navbar navbar-expand-lg navbar-dark navbar-corporate mb-4">

        <div class="container-fluid px-3 px-lg-4">


            <!-- LOGO -->

            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="index.php?route=dashboard">

                <span class="brand-mark text-white">

                    <i class="fa-solid fa-house-chimney-window"></i>

                </span>

                <span>

                    Casa

                    <span class="fw-normal text-white-50">
                        Organizada
                    </span>

                </span>

            </a>


            <!-- BOTÃO MOBILE -->

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav">

                <span class="navbar-toggler-icon"></span>

            </button>


            <div class="collapse navbar-collapse" id="nav">


                <!-- MENU -->

                <ul class="navbar-nav gap-1 ms-lg-3">


                    <li class="nav-item">

                        <a class="nav-link active fw-semibold text-white" href="index.php?route=dashboard">

                            <i class="fa-solid fa-chart-line me-1"></i>

                            Dashboard

                        </a>

                    </li>


                    <li class="nav-item">

                        <a class="nav-link text-white-50" href="index.php?route=receitas">

                            <i class="fa-solid fa-money-bill-trend-up me-1"></i>

                            Receitas

                        </a>

                    </li>


                    <li class="nav-item">

                        <a class="nav-link text-white-50" href="index.php?route=despesas">

                            <i class="fa-solid fa-wallet me-1"></i>

                            Despesas

                        </a>

                    </li>


                    <li class="nav-item">

                        <a class="nav-link text-white-50" href="index.php?route=cartoes">

                            <i class="fa-solid fa-credit-card me-1"></i>

                            Cartões

                        </a>

                    </li>


                    <li class="nav-item">

                        <a class="nav-link text-white-50" href="index.php?route=parcelamentos">

                            <i class="fa-solid fa-calendar-days me-1"></i>

                            Parcelamentos

                        </a>

                    </li>


                    <li class="nav-item">

                        <a class="nav-link text-white-50" href="index.php?route=membros">

                            <i class="fa-solid fa-users me-1"></i>

                            Família

                        </a>

                    </li>


                </ul>


                <!-- ÁREA DIREITA -->

                <div class="ms-auto d-flex align-items-center gap-2 mt-3 mt-lg-0">


                    <!-- NOVA RECEITA -->

                    <a class="btn btn-success btn-sm px-3 rounded-2 fw-medium" href="index.php?route=receitas">

                        <i class="fa-solid fa-plus me-1"></i>

                        Receita

                    </a>


                    <!-- NOVA DESPESA -->

                    <a class="btn btn-danger btn-sm px-3 rounded-2 fw-medium" href="index.php?route=despesas">

                        <i class="fa-solid fa-plus me-1"></i>

                        Despesa

                    </a>


                    <!-- USUÁRIO -->

                    <div class="dropdown">


                        <button class="btn btn-outline-light btn-sm dropdown-toggle px-3" type="button"
                            data-bs-toggle="dropdown">

                            <i class="fa-solid fa-user me-1"></i>

                            <?= $esc($usuarioNome) ?>

                        </button>


                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">


                            <li class="px-3 py-2">


                                <div class="fw-bold">

                                    <?= $esc($usuarioNome) ?>

                                </div>


                                <?php if (!empty($usuarioEmail)): ?>

                                <small class="text-muted">

                                    <?= $esc($usuarioEmail) ?>

                                </small>

                                <?php endif; ?>


                            </li>


                            <li>

                                <hr class="dropdown-divider">

                            </li>


                            <li>

                                <a class="dropdown-item text-danger" href="index.php?route=logout"
                                    onclick="return confirm('Deseja realmente sair do sistema?')">

                                    <i class="fa-solid fa-right-from-bracket me-2"></i>

                                    Sair do sistema

                                </a>

                            </li>


                        </ul>


                    </div>


                </div>


            </div>


        </div>

    </nav>


    <!-- =========================================================
     CONTEÚDO
========================================================= -->

    <main class="page">


        <!-- CABEÇALHO -->

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">


            <div>

                <h1 class="h3 fw-bold mb-1">

                    Dashboard financeiro

                </h1>


                <p class="text-secondary mb-0">

                    Visão geral do período —

                    <strong>

                        <?= $esc($nomesMeses[$month]) ?>

                        de

                        <?= $esc($year) ?>

                    </strong>

                </p>


            </div>


            <!-- FILTRO -->

            <form class="d-flex gap-2 bg-white p-2 rounded-3 border shadow-sm" method="get">


                <input type="hidden" name="route" value="dashboard">


                <select name="mes" class="form-select form-select-sm border-0 bg-light fw-medium">


                    <?php for ($m = 1; $m <= 12; $m++): ?>


                    <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>

                        <?= $nomesMeses[$m] ?>

                    </option>


                    <?php endfor; ?>


                </select>


                <input type="number" name="ano" class="form-control form-control-sm border-0 bg-light fw-medium"
                    style="width:90px" value="<?= $year ?>" min="2020" max="2100">


                <button class="btn btn-primary btn-sm px-3 fw-medium">

                    Filtrar

                </button>


            </form>


        </div>


        <!-- =====================================================
         CARDS
    ===================================================== -->

        <div class="row g-3 mb-4">


            <!-- RECEITAS -->

            <div class="col-6 col-xl-3">

                <div class="cardx metric h-100">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="metric-label">

                                Receitas

                            </div>


                            <div class="metric-value text-success">

                                <?= $money($totalReceitas) ?>

                            </div>

                        </div>


                        <span class="iconx soft-green">

                            <i class="fa-solid fa-arrow-trend-up"></i>

                        </span>


                    </div>

                </div>

            </div>


            <!-- DESPESAS -->

            <div class="col-6 col-xl-3">

                <div class="cardx metric h-100">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="metric-label">

                                Despesas realizadas

                            </div>


                            <div class="metric-value text-danger">

                                <?= $money($totalDespesasRealizadas) ?>

                            </div>

                        </div>


                        <span class="iconx soft-red">

                            <i class="fa-solid fa-arrow-trend-down"></i>

                        </span>


                    </div>

                </div>

            </div>


            <!-- SALDO REAL -->

            <div class="col-6 col-xl-3">

                <div class="cardx metric h-100">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="metric-label">

                                Saldo realizado

                            </div>


                            <div class="metric-value <?= $saldoReal >= 0 ? 'text-success' : 'text-danger' ?>">

                                <?= $money($saldoReal) ?>

                            </div>

                        </div>


                        <span class="iconx <?= $saldoReal >= 0 ? 'soft-green' : 'soft-red' ?>">

                            <i class="fa-solid fa-wallet"></i>

                        </span>


                    </div>

                </div>

            </div>


            <!-- SALDO PREVISTO -->

            <div class="col-6 col-xl-3">

                <div class="cardx metric h-100">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="metric-label">

                                Saldo previsto

                            </div>


                            <div class="metric-value <?= $saldoPrevisto >= 0 ? 'text-primary' : 'text-danger' ?>">

                                <?= $money($saldoPrevisto) ?>

                            </div>

                        </div>


                        <span class="iconx soft-blue">

                            <i class="fa-solid fa-calendar-check"></i>

                        </span>


                    </div>

                </div>

            </div>


        </div>


        <!-- =====================================================
         GRÁFICOS
    ===================================================== -->

        <div class="row g-4 mb-4">


            <!-- RECEITAS X DESPESAS -->

            <div class="col-xl-6">

                <div class="cardx h-100">


                    <div class="headerx">


                        <strong class="text-dark">

                            <i class="fa-solid fa-chart-column me-2 text-primary"></i>

                            Receitas × Despesas

                        </strong>


                        <div class="text-secondary small">

                            Comparativo mensal

                        </div>


                    </div>


                    <div class="bodyx">

                        <div class="chart">

                            <canvas id="chartRD"></canvas>

                        </div>

                    </div>


                </div>

            </div>


            <!-- DESPESAS POR CATEGORIA -->

            <div class="col-xl-6">

                <div class="cardx h-100">


                    <div class="headerx">


                        <strong class="text-dark">

                            <i class="fa-solid fa-chart-pie me-2 text-secondary"></i>

                            Despesas por categoria

                        </strong>


                        <div class="text-secondary small">

                            Distribuição realizada

                        </div>


                    </div>


                    <div class="bodyx">

                        <div class="chart-small">

                            <canvas id="chartCat"></canvas>

                        </div>

                    </div>


                </div>

            </div>


        </div>


        <!-- =====================================================
         TABELA E CALENDÁRIO
    ===================================================== -->

        <div class="row g-4 mb-4">


            <!-- ÚLTIMAS DESPESAS -->

            <div class="col-xl-8">

                <div class="cardx h-100">


                    <div class="headerx">


                        <strong class="text-dark">

                            <i class="fa-solid fa-list me-2 text-muted"></i>

                            Últimas despesas

                        </strong>


                        <a href="index.php?route=despesas" class="btn btn-sm btn-outline-danger rounded-2">

                            Ver todas

                        </a>


                    </div>


                    <div class="table-responsive">


                        <table class="table table-hover align-middle mb-0">


                            <thead>

                                <tr>

                                    <th>Data</th>

                                    <th>Descrição</th>

                                    <th>Categoria</th>

                                    <th>Pagamento</th>

                                    <th class="text-end">

                                        Valor

                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (array_slice($despesas, 0, 8) as $d): ?>


                                <?php

                                $dataDespesa =
                                    $d['data']
                                    ?? $d['data_lancamento']
                                    ?? date('Y-m-d');

                                $valorDespesa =
                                    $d['valor_real']
                                    ?? $d['valor_realizado']
                                    ?? $d['valor']
                                    ?? 0;

                                ?>


                                <tr>


                                    <td class="text-nowrap">

                                        <?= $esc(
                                            date(
                                                'd/m/Y',
                                                strtotime($dataDespesa)
                                            )
                                        ) ?>

                                    </td>


                                    <td class="fw-semibold text-dark">

                                        <?= $esc(
                                            $d['descricao']
                                            ?? 'Sem descrição'
                                        ) ?>

                                    </td>


                                    <td>

                                        <span class="badge bg-light text-dark border">

                                            <?= $esc(
                                                $d['categoria']
                                                ?? 'Outros'
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?= $esc(
                                            $d['forma_pagamento']
                                            ?? 'Não informado'
                                        ) ?>

                                    </td>


                                    <td class="text-end text-danger fw-bold">

                                        <?= $money($valorDespesa) ?>

                                    </td>


                                </tr>


                                <?php endforeach; ?>


                                <?php if (empty($despesas)): ?>


                                <tr>

                                    <td colspan="5" class="text-center text-secondary py-5">

                                        <i class="fa-solid fa-receipt fs-3 d-block mb-2 text-muted"></i>

                                        Nenhuma despesa registrada
                                        no período.

                                    </td>

                                </tr>


                                <?php endif; ?>


                            </tbody>


                        </table>


                    </div>


                </div>

            </div>


            <!-- =================================================
             CALENDÁRIO
        ================================================= -->

            <div class="col-xl-4">

                <div class="cardx h-100">


                    <div class="headerx">


                        <strong class="text-dark">

                            <i class="fa-solid fa-calendar-days me-2 text-primary"></i>

                            Agenda e anotações

                        </strong>


                    </div>


                    <div class="bodyx">


                        <!-- NAVEGAÇÃO -->

                        <div class="d-flex justify-content-between align-items-center mb-3">


                            <button class="btn btn-sm btn-light border rounded-circle" id="prevMonth" type="button"
                                style="width:32px;height:32px">

                                ‹

                            </button>


                            <strong id="calendarTitle" class="text-capitalize text-dark"></strong>


                            <button class="btn btn-sm btn-light border rounded-circle" id="nextMonth" type="button"
                                style="width:32px;height:32px">

                                ›

                            </button>


                        </div>


                        <!-- DIAS DA SEMANA -->

                        <div class="calendar-week">


                            <?php foreach (
                            [
                                'Dom',
                                'Seg',
                                'Ter',
                                'Qua',
                                'Qui',
                                'Sex',
                                'Sáb'
                            ]
                            as $dia
                        ): ?>


                            <div>

                                <?= $dia ?>

                            </div>


                            <?php endforeach; ?>


                        </div>


                        <!-- CALENDÁRIO -->

                        <div id="calendar" class="calendar-grid mb-3"></div>


                        <!-- FORMULÁRIO DE ANOTAÇÃO -->

                        <form method="post" action="index.php?route=salvar_anotacao">


                            <input type="hidden" name="data_agendamento" id="noteDate" value="<?= date('Y-m-d') ?>">


                            <div class="input-group input-group-sm">


                                <input name="texto" class="form-control rounded-start-2" placeholder="Novo lembrete..."
                                    required>


                                <button class="btn btn-primary rounded-end-2 px-3" type="submit">

                                    Salvar

                                </button>


                            </div>


                        </form>


                        <!-- LISTA DE ANOTAÇÕES -->

                        <div class="mt-3 pe-1" style="max-height:170px; overflow-y:auto">


                            <?php foreach ($anotacoes as $n): ?>


                            <div class="p-2 mb-2 bg-light rounded-2 border-start border-3 border-primary">


                                <small class="text-primary fw-bold d-block">


                                    <?php

                                    $dataAnotacao =
                                        $n['data_agendamento']
                                        ?? date('Y-m-d');

                                    ?>


                                    <?= $esc(
                                        date(
                                            'd/m/Y',
                                            strtotime($dataAnotacao)
                                        )
                                    ) ?>


                                </small>


                                <div class="small text-dark">

                                    <?= $esc(
                                        $n['texto']
                                        ?? ''
                                    ) ?>

                                </div>


                            </div>


                            <?php endforeach; ?>


                            <?php if (empty($anotacoes)): ?>


                            <div class="small text-secondary text-center py-3">

                                Nenhuma anotação para o período.

                            </div>


                            <?php endif; ?>


                        </div>


                    </div>


                </div>

            </div>


        </div>


    </main>


    <!-- =========================================================
     JAVASCRIPT
========================================================= -->

    <script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {


            /* =====================================================
               GRÁFICO RECEITAS X DESPESAS
            ===================================================== */

            const chartRD = document.getElementById('chartRD');


            if (chartRD) {

                new Chart(
                    chartRD, {

                        type: 'bar',

                        data: {

                            labels: [
                                'Receitas',
                                'Despesas'
                            ],

                            datasets: [

                                {

                                    label: 'Valores',

                                    data: [

                                        <?= json_encode($totalReceitas) ?>,

                                        <?= json_encode($totalDespesasRealizadas) ?>

                                    ],

                                    backgroundColor: [

                                        '#16a34a',

                                        '#dc2626'

                                    ],

                                    borderRadius: 8,

                                    barThickness: 50

                                }

                            ]

                        },


                        options: {

                            responsive: true,

                            maintainAspectRatio: false,


                            plugins: {

                                legend: {

                                    display: false

                                },


                                tooltip: {

                                    callbacks: {

                                        label: function(context) {

                                            return (
                                                ' R$ ' +
                                                Number(
                                                    context.raw
                                                ).toLocaleString(
                                                    'pt-BR', {

                                                        minimumFractionDigits: 2,

                                                        maximumFractionDigits: 2

                                                    }
                                                )
                                            );

                                        }

                                    }

                                }

                            },


                            scales: {

                                y: {

                                    beginAtZero: true,

                                    ticks: {

                                        callback: function(value) {

                                            return (
                                                'R$ ' +
                                                Number(value).toLocaleString(
                                                    'pt-BR'
                                                )
                                            );

                                        }

                                    }

                                },


                                x: {

                                    grid: {

                                        display: false

                                    }

                                }

                            }

                        }

                    }
                );

            }


            /* =====================================================
               GRÁFICO DESPESAS POR CATEGORIA
            ===================================================== */

            const chartCat =
                document.getElementById('chartCat');


            const catLabels =
                <?= $chartDespLabels ?: '[]' ?>;


            const catData =
                <?= $chartDespData ?: '[]' ?>;


            if (chartCat) {

                new Chart(
                    chartCat, {

                        type: 'doughnut',

                        data: {

                            labels: catLabels,

                            datasets: [

                                {

                                    data: catData,

                                    backgroundColor: [

                                        '#2563eb',

                                        '#dc2626',

                                        '#d97706',

                                        '#16a34a',

                                        '#7c3aed',

                                        '#0891b2',

                                        '#db2777',

                                        '#64748b'

                                    ],

                                    borderColor: '#ffffff',

                                    borderWidth: 2

                                }

                            ]

                        },


                        options: {

                            responsive: true,

                            maintainAspectRatio: false,

                            cutout: '68%',


                            plugins: {

                                legend: {

                                    position: 'bottom'

                                }

                            }

                        }

                    }
                );

            }


            /* =====================================================
               CALENDÁRIO
            ===================================================== */

            const noteDates =
                <?= json_encode(
                $agendaDates,
                JSON_UNESCAPED_UNICODE
            ) ?>;


            let cal = new Date(

                <?= json_encode($year) ?>,

                <?= json_encode($month - 1) ?>,

                1

            );


            let selected =
                '<?= date('Y-m-d') ?>';


            const calendar =
                document.getElementById('calendar');


            const title =
                document.getElementById('calendarTitle');


            function iso(year, month, day) {

                return (
                    year +
                    '-' +
                    String(month + 1).padStart(2, '0') +
                    '-' +
                    String(day).padStart(2, '0')
                );

            }


            function renderCalendar() {


                const year =
                    cal.getFullYear();


                const month =
                    cal.getMonth();


                const firstDay =
                    new Date(
                        year,
                        month,
                        1
                    ).getDay();


                const lastDay =
                    new Date(
                        year,
                        month + 1,
                        0
                    ).getDate();


                title.textContent =
                    new Intl.DateTimeFormat(
                        'pt-BR', {

                            month: 'long',

                            year: 'numeric'

                        }
                    ).format(cal);


                calendar.innerHTML =
                    '';


                /* Espaços antes do primeiro dia */

                for (
                    let i = 0; i < firstDay; i++
                ) {

                    const empty =
                        document.createElement('div');


                    empty.className =
                        'calendar-day';


                    empty.style.visibility =
                        'hidden';


                    calendar.appendChild(
                        empty
                    );

                }


                /* Dias */

                for (
                    let day = 1; day <= lastDay; day++
                ) {


                    const element =
                        document.createElement('button');


                    element.type =
                        'button';


                    element.className =
                        'calendar-day';


                    const currentDate =
                        iso(
                            year,
                            month,
                            day
                        );


                    element.textContent =
                        day;


                    if (
                        currentDate === selected
                    ) {

                        element.classList.add(
                            'selected'
                        );

                    }


                    if (
                        noteDates.includes(
                            currentDate
                        )
                    ) {

                        element.classList.add(
                            'has-note'
                        );

                    }


                    const today =
                        new Date();


                    if (

                        today.getFullYear() === year

                        &&

                        today.getMonth() === month

                        &&

                        today.getDate() === day

                    ) {

                        element.classList.add(
                            'today'
                        );

                    }


                    element.onclick =
                        function() {


                            selected =
                                currentDate;


                            document.getElementById(
                                    'noteDate'
                                ).value =
                                currentDate;


                            renderCalendar();

                        };


                    calendar.appendChild(
                        element
                    );


                }


            }


            /* MÊS ANTERIOR */

            const prevButton =
                document.getElementById(
                    'prevMonth'
                );


            if (prevButton) {

                prevButton.onclick =
                    function() {

                        cal.setMonth(
                            cal.getMonth() - 1
                        );

                        renderCalendar();

                    };

            }


            /* PRÓXIMO MÊS */

            const nextButton =
                document.getElementById(
                    'nextMonth'
                );


            if (nextButton) {

                nextButton.onclick =
                    function() {

                        cal.setMonth(
                            cal.getMonth() + 1
                        );

                        renderCalendar();

                    };

            }


            renderCalendar();


        }
    );
    </script>


    <!-- Bootstrap JS -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>