<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguranca.php';
exigirLogin();

/**
 * CasaOrganizada — Gestão de Despesas
 */

$esc = static fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn($v) => 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.');

$despesasCategorias = $despesasCategorias ?? [];
$categoriasDespesa  = $categoriasDespesa ?? [];
$cartoes            = $cartoes ?? [];
$despesas           = $despesas ?? [];
$totalDespesasRealizadas = $totalDespesasRealizadas ?? 0.0;

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$catLabels = json_encode(array_keys($despesasCategorias), $jsonFlags);
$catData   = json_encode(array_values($despesasCategorias), $jsonFlags);
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CasaOrganizada | Despesas</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
    <?php require __DIR__ . '/../assets/css/app.css';

    ?> :root {
        --co-navy: #172033;
        --co-blue: #2563eb;
        --co-red: #dc2626;
        --co-text: #172033;
        --co-muted: #64748b;
        --co-border: #e5e7eb;
        --co-bg: #f5f7fb;
        --co-card: #ffffff;
        --co-radius: 14px;
        --co-shadow: 0 4px 18px rgba(15, 23, 42, .05);
    }

    body {
        background-color: var(--co-bg);
        color: var(--co-text);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        font-size: 0.875rem;
        margin: 0;
    }

    .navbar-corporate {
        background: var(--co-navy);
        min-height: 64px;
        box-shadow: 0 2px 12px rgba(15, 23, 42, .14);
    }

    .brand-mark {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: var(--co-blue);
    }

    .page-shell {
        max-width: 1550px;
        margin: 0 auto;
        padding: 24px;
    }

    .cardx {
        background: var(--co-card);
        border: 1px solid var(--co-border);
        border-radius: var(--co-radius);
        box-shadow: var(--co-shadow);
        overflow: hidden;
    }

    .headerx {
        padding: 16px 20px;
        background: #ffffff;
        border-bottom: 1px solid var(--co-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .bodyx {
        padding: 20px;
    }

    .chart-small {
        position: relative;
        height: 280px;
    }

    .table-corporate th {
        background: #f8fafc;
        color: var(--co-muted);
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 700;
        border-bottom: 1px solid var(--co-border);
        white-space: nowrap;
    }

    .table-corporate td {
        vertical-align: middle;
        padding: 12px 16px;
        border-color: #eef1f5;
    }

    .badge-soft-danger {
        background-color: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .form-label {
        font-weight: 600;
        color: #334155;
        font-size: 0.8rem;
        margin-bottom: 4px;
    }

    .form-control,
    .form-select {
        border-radius: 8px;
        border-color: var(--co-border);
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--co-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-corporate mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="index.php?route=dashboard">
                <span class="brand-mark text-white"><i class="fa-solid fa-house-chimney-window"></i></span>
                <span>Casa<span class="fw-normal text-white-50">Organizada</span></span>
            </a>
            <div class="navbar-nav ms-auto gap-2">
                <a class="nav-link text-white-50" href="index.php?route=dashboard">Dashboard</a>
                <a class="nav-link text-white-50" href="index.php?route=receitas">Receitas</a>
                <a class="nav-link active fw-semibold text-white" href="index.php?route=despesas">Despesas</a>
                <a class="nav-link text-white-50" href="index.php?route=cartoes">Cartões</a>
                <a class="nav-link text-white-50" href="index.php?route=parcelamentos">Parcelamentos</a>
                <a class="nav-link text-white-50" href="index.php?route=membros">Família</a>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Despesas</h1>
                <p class="text-secondary mb-0">Gerencie todas as saídas financeiras e anexos de comprovantes.</p>
            </div>
            <a href="index.php?route=dashboard" class="btn btn-outline-primary rounded-3 px-3">
                <i class="fa-solid fa-chart-line me-1"></i> Dashboard
            </a>
        </div>

        <div class="row g-4 mb-4">
            <!-- Formulário Nova Despesa -->
            <div class="col-xl-7">
                <div class="cardx">
                    <div class="headerx">
                        <strong class="text-dark"><i class="fa-solid fa-plus-circle text-danger me-2"></i>Nova
                            despesa</strong>
                    </div>
                    <div class="bodyx">
                        <form method="post" action="index.php?route=despesas_store" enctype="multipart/form-data"
                            class="row g-3">
                            <?= csrfCampo() ?>
                            <div class="col-md-6">
                                <label class="form-label">Descrição</label>
                                <input type="text" name="descricao" class="form-control"
                                    placeholder="Ex: Mercado, Luz, Internet" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Valor</label>
                                <input type="number" name="valor" step="0.01" min="0.01" class="form-control"
                                    placeholder="0,00" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Data</label>
                                <input type="date" name="data" value="<?= date('Y-m-d') ?>" class="form-control"
                                    required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Categoria</label>
                                <select name="categoria_id" class="form-select">
                                    <option value="">Outros</option>
                                    <?php foreach ($categoriasDespesa as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $esc($c['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Forma de pagamento</label>
                                <select name="forma_pagamento" id="formaPagamento" class="form-select" required>
                                    <option value="PIX">PIX</option>
                                    <option value="Dinheiro">Dinheiro</option>
                                    <option value="Cartao de Debito">Cartão de Débito</option>
                                    <option value="Cartao de Credito">Cartão de Crédito</option>
                                    <option value="Boleto">Boleto</option>
                                </select>
                            </div>

                            <div class="col-md-6" id="cartaoBox" style="display:none">
                                <label class="form-label">Cartão</label>
                                <select name="cartao_id" class="form-select">
                                    <option value="">Selecione</option>
                                    <?php foreach ($cartoes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $esc($c['nome']) ?> — limite
                                        <?= $money($c['limite_total']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="Variavel">Variável</option>
                                    <option value="Fixa">Fixa</option>
                                </select>
                            </div>

                            <!-- Upload de Comprovante -->
                            <div class="col-12">
                                <label class="form-label">Comprovante de pagamento</label>
                                <input type="file" name="comprovante" class="form-control" accept="image/*,.pdf">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Observação</label>
                                <textarea name="observacao" class="form-control" rows="2"
                                    placeholder="Notas adicionais sobre este pagamento..."></textarea>
                            </div>

                            <div class="col-12 text-end pt-2">
                                <button type="submit" class="btn btn-danger px-4 rounded-3">
                                    <i class="fa-solid fa-minus me-1"></i> Salvar despesa
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Categorias -->
            <div class="col-xl-5">
                <div class="cardx h-100">
                    <div class="headerx">
                        <strong class="text-dark"><i class="fa-solid fa-chart-pie text-secondary me-2"></i>Despesas por
                            categoria</strong>
                    </div>
                    <div class="bodyx d-flex align-items-center justify-content-center">
                        <div class="chart-small w-100">
                            <canvas id="catChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela do Histórico -->
        <div class="cardx">
            <div class="headerx">
                <strong class="text-dark"><i class="fa-solid fa-clock-rotate-left me-2"></i>Histórico do
                    período</strong>
                <span class="badge bg-danger-subtle text-danger fs-6 px-3 py-2 rounded-pill">
                    Total: <?= $money($totalDespesasRealizadas) ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-corporate align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Pagamento</th>
                            <th>Status</th>
                            <th class="text-center">Comprovante</th>
                            <th class="text-end">Valor</th>
                            <th class="text-center" style="width: 50px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($despesas as $d): ?>
                        <tr>
                            <td class="text-nowrap"><?= $esc(date('d/m/Y', strtotime($d['data']))) ?></td>
                            <td class="fw-semibold text-dark"><?= $esc($d['descricao']) ?></td>
                            <td><span class="badge-soft-danger"><?= $esc($d['categoria'] ?? 'Outros') ?></span></td>
                            <td>
                                <?= $esc($d['forma_pagamento']) ?>
                                <?php if (!empty($d['cartao_nome'])): ?>
                                <small class="text-muted d-block">— <?= $esc($d['cartao_nome']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span
                                    class="badge bg-light text-dark border"><?= $esc($d['status'] ?? 'Realizada') ?></span>
                            </td>

                            <!-- Coluna do Comprovante -->
                            <td class="text-center">
                                <?php if (!empty($d['comprovante'])): ?>
                                <a href="<?= $esc($d['comprovante']) ?>" target="_blank"
                                    class="btn btn-sm btn-outline-secondary rounded-circle" title="Ver Comprovante">
                                    <i class="fa-solid fa-paperclip"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end text-danger fw-bold text-nowrap">
                                <?= $money($d['valor_real'] ?? $d['valor'] ?? 0) ?></td>
                            <td class="text-center">
                                <form method="post" action="index.php?route=excluir_despesa"
                                    onsubmit="return confirm('Tem certeza que deseja excluir esta despesa?')">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($despesas)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-5">
                                <i class="fa-solid fa-receipt fs-3 d-block mb-2 text-muted"></i>
                                Nenhuma despesa registrada no período.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const labels = <?= $catLabels ?: '[]' ?>;
        const data = <?= $catData ?: '[]' ?>;

        if (document.getElementById('catChart')) {
            new Chart(document.getElementById('catChart'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: ['#2563eb', '#dc2626', '#d97706', '#16a34a', '#7c3aed',
                            '#0891b2', '#db2777', '#64748b'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: "'Inter', sans-serif",
                                    size: 12
                                },
                                boxWidth: 12
                            }
                        }
                    }
                }
            });
        }

        const forma = document.getElementById('formaPagamento');
        const box = document.getElementById('cartaoBox');

        function toggleCard() {
            if (forma && box) {
                box.style.display = forma.value === 'Cartao de Credito' ? 'block' : 'none';
            }
        }

        if (forma) {
            forma.addEventListener('change', toggleCard);
            toggleCard();
        }
    });
    </script>
</body>

</html>