<?php
declare(strict_types=1);

/**
 * CasaOrganizada — Gestão de Receitas
 */

$esc = static fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn($v) => 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.');

$categoriasReceita = $categoriasReceita ?? [];
$receitas          = $receitas ?? [];
$totalReceitas     = $totalReceitas ?? 0.0;
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CasaOrganizada | Receitas</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>
    <?php require __DIR__ . '/../assets/css/app.css';

    ?> :root {
        --co-navy: #172033;
        --co-blue: #2563eb;
        --co-green: #16a34a;
        --co-text: #1e293b;
        --co-muted: #64748b;
        --co-border: #e2e8f0;
        --co-bg: #f8fafc;
        --co-card: #ffffff;
        --co-radius: 12px;
        --co-shadow: 0 4px 16px rgba(15, 23, 42, .04);
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
        box-shadow: 0 2px 12px rgba(15, 23, 42, .12);
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

    .page {
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
        border-color: #f1f5f9;
    }

    .badge-soft-success {
        background-color: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
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
        border-color: var(--co-green);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }

    /* Card Feed Horizontal */
    .recent-item-card {
        background: #ffffff;
        border: 1px solid var(--co-border);
        border-radius: 10px;
        padding: 14px 16px;
        transition: all 0.2s ease;
    }

    .recent-item-card:hover {
        border-color: #bbf7d0;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.08);
        transform: translateY(-2px);
    }

    .icon-circle-success {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #f0fdf4;
        color: #16a34a;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
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
                <a class="nav-link active fw-semibold text-white" href="index.php?route=receitas">Receitas</a>
                <a class="nav-link text-white-50" href="index.php?route=despesas">Despesas</a>
            </div>
        </div>
    </nav>

    <main class="page">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Receitas</h1>
                <p class="text-secondary mb-0">Gerencie todas as entradas financeiras e anexos de comprovantes.</p>
            </div>
            <a href="index.php?route=dashboard" class="btn btn-outline-primary rounded-3 px-3">
                <i class="fa-solid fa-chart-line me-1"></i> Dashboard
            </a>
        </div>

        <div class="row g-4 mb-4">
            <!-- Formulário Nova Receita -->
            <div class="col-xl-5">
                <div class="cardx">
                    <div class="headerx">
                        <strong class="text-dark"><i class="fa-solid fa-plus-circle text-success me-2"></i>Nova
                            receita</strong>
                    </div>
                    <div class="bodyx">
                        <form method="post" action="index.php?route=receitas_store" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Descrição</label>
                                <input type="text" name="descricao" class="form-control"
                                    placeholder="Ex: Salário, Freelance, Rendimentos" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Categoria</label>
                                <select name="categoria_id" class="form-select">
                                    <option value="">Outros</option>
                                    <?php foreach ($categoriasReceita as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $esc($c['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Valor</label>
                                    <input type="number" name="valor" step="0.01" min="0.01" class="form-control"
                                        placeholder="0,00" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Data</label>
                                    <input type="date" name="data_receita" value="<?= date('Y-m-d') ?>"
                                        class="form-control" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="Variavel">Variável</option>
                                    <option value="Fixa">Fixa</option>
                                </select>
                            </div>

                            <!-- Upload do Comprovante de Receita -->
                            <div class="mb-3">
                                <label class="form-label">Comprovante / Recibo</label>
                                <input type="file" name="comprovante" class="form-control" accept="image/*,.pdf">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observação</label>
                                <textarea name="observacao" class="form-control" rows="2"
                                    placeholder="Notas adicionais sobre esta receita..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-success w-100 rounded-3 py-2 fw-medium">
                                <i class="fa-solid fa-check me-1"></i> Salvar receita
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tabela de Receitas -->
            <div class="col-xl-7">
                <div class="cardx">
                    <div class="headerx">
                        <strong class="text-dark"><i
                                class="fa-solid fa-money-bill-trend-up text-success me-2"></i>Receitas do mês</strong>
                        <span class="badge bg-success-subtle text-success fs-6 px-3 py-2 rounded-pill">
                            Total: <?= $money($totalReceitas) ?>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-corporate align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th>Categoria</th>
                                    <th class="text-center">Comprovante</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-center" style="width: 50px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($receitas as $r): ?>
                                <?php $dataExibicao = $r['data_receita'] ?? $r['data'] ?? date('Y-m-d'); ?>
                                <tr>
                                    <td class="text-nowrap"><?= $esc(date('d/m/Y', strtotime($dataExibicao))) ?></td>
                                    <td class="fw-semibold text-dark"><?= $esc($r['descricao']) ?></td>
                                    <td><span class="badge-soft-success"><?= $esc($r['categoria'] ?? 'Outros') ?></span>
                                    </td>

                                    <!-- Coluna do Comprovante -->
                                    <td class="text-center">
                                        <?php if (!empty($r['comprovante'])): ?>
                                        <a href="<?= $esc($r['comprovante']) ?>" target="_blank"
                                            class="btn btn-sm btn-outline-secondary rounded-circle"
                                            title="Ver Comprovante">
                                            <i class="fa-solid fa-paperclip"></i>
                                        </a>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end text-success fw-bold text-nowrap"><?= $money($r['valor']) ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="post" action="index.php?route=receitas_delete"
                                            onsubmit="return confirm('Tem certeza que deseja excluir esta receita?')">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-danger border-0 rounded-circle">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($receitas)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-5">
                                        <i class="fa-solid fa-hand-holding-dollar fs-3 d-block mb-2 text-muted"></i>
                                        Nenhuma receita registrada no período.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD HORIZONTAL: ÚLTIMAS RECEITAS REGISTRADAS -->
        <div class="row">
            <div class="col-12">
                <div class="cardx">
                    <div class="headerx">
                        <strong class="text-dark">
                            <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Últimas Receitas Lançadas
                        </strong>
                        <span class="text-muted small">Exibindo os últimos registros</span>
                    </div>
                    <div class="bodyx">
                        <div class="row g-3">
                            <?php 
                            $ultimasReceitas = array_slice($receitas, 0, 4);
                            foreach ($ultimasReceitas as $ur): 
                                $dt = $ur['data_receita'] ?? $ur['data'] ?? date('Y-m-d');
                            ?>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="recent-item-card d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-circle-success">
                                            <i class="fa-solid fa-arrow-up"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark text-truncate" style="max-width: 140px;">
                                                <?= $esc($ur['descricao']) ?></div>
                                            <div class="small text-muted d-flex align-items-center gap-2">
                                                <span><?= $esc(date('d/m/Y', strtotime($dt))) ?></span>
                                                <span>•</span>
                                                <span
                                                    class="badge bg-light text-secondary border"><?= $esc($ur['categoria'] ?? 'Geral') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success"><?= $money($ur['valor']) ?></div>
                                        <?php if (!empty($ur['comprovante'])): ?>
                                        <a href="<?= $esc($ur['comprovante']) ?>" target="_blank"
                                            class="small text-decoration-none text-primary fw-medium">
                                            <i class="fa-solid fa-paperclip me-1"></i>Anexo
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if (empty($ultimasReceitas)): ?>
                            <div class="col-12 text-center text-muted py-3">
                                <i class="fa-solid fa-inbox fs-4 d-block mb-1"></i>
                                Nenhum histórico recente disponível.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>