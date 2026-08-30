<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguranca.php';
exigirLogin();

/**
 * CasaOrganizada — Gestão de Parcelamentos
 */

$esc = static fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn($v) => 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.');

$parcelamentos = $parcelamentos ?? [];
$parcelasPorDespesa = $parcelasPorDespesa ?? [];
$cartoes = $cartoes ?? [];
$categoriasDespesa = $categoriasDespesa ?? [];
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CasaOrganizada | Parcelamentos</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>
    <?php require __DIR__ . '/../assets/css/app.css'; ?>

    body {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        font-size: 0.875rem;
    }

    .navbar-corporate {
        background: var(--navy);
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
        background: var(--blue);
    }

    .page-shell {
        max-width: 1550px;
        margin: 0 auto;
        padding: 24px;
    }

    .progress {
        height: 8px;
        border-radius: 999px;
    }

    .parcela-pill {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .68rem;
        font-weight: 700;
    }

    .parcela-pill.pago {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }

    .parcela-pill.pendente {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
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
                <a class="nav-link text-white-50" href="index.php?route=despesas">Despesas</a>
                <a class="nav-link text-white-50" href="index.php?route=cartoes">Cartões</a>
                <a class="nav-link active fw-semibold text-white" href="index.php?route=parcelamentos">Parcelamentos</a>
                <a class="nav-link text-white-50" href="index.php?route=membros">Família</a>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Parcelamentos</h1>
                <p class="text-secondary mb-0">Compras parceladas no cartão e o andamento de cada parcela.</p>
            </div>
            <button class="btn btn-primary rounded-3 px-3" type="button" data-bs-toggle="modal"
                data-bs-target="#modalNovoParcelamento">
                <i class="fa-solid fa-plus me-1"></i> Novo parcelamento
            </button>
        </div>

        <div class="cardx">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Compra</th>
                            <th>Categoria</th>
                            <th>Cartão</th>
                            <th>Início</th>
                            <th class="text-end">Valor total</th>
                            <th>Progresso</th>
                            <th>Parcelas</th>
                            <th class="text-center" style="width: 60px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parcelamentos as $p): ?>
                        <?php
                        $totalParcelas = (int)$p['total_parcelas'];
                        $pagas = (int)$p['parcelas_pagas'];
                        $percentual = $totalParcelas > 0 ? round(($pagas / $totalParcelas) * 100) : 0;
                        ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?= $esc($p['descricao']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $esc($p['categoria_nome'] ?? 'Outros') ?></span></td>
                            <td><?= $esc($p['cartao_nome'] ?? 'Não informado') ?></td>
                            <td class="text-nowrap"><?= $esc(date('d/m/Y', strtotime((string)$p['data_prevista']))) ?></td>
                            <td class="text-end fw-bold"><?= $money($p['valor_previsto']) ?></td>
                            <td style="min-width: 160px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1">
                                        <div class="progress-bar bg-success" style="width: <?= $percentual ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= $pagas ?>/<?= $totalParcelas ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($parcelasPorDespesa[$p['despesa_id']] ?? [] as $parcela): ?>
                                    <?php if ($parcela['status'] === 'Pago'): ?>
                                    <span class="parcela-pill pago" title="Parcela <?= (int)$parcela['numero_parcela'] ?> paga">
                                        <?= (int)$parcela['numero_parcela'] ?>
                                    </span>
                                    <?php else: ?>
                                    <form method="post" action="index.php?route=parcela_pagar"
                                        title="Marcar parcela <?= (int)$parcela['numero_parcela'] ?> como paga"
                                        onsubmit="return confirm('Confirmar pagamento desta parcela?')">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="id" value="<?= (int)$parcela['id'] ?>">
                                        <button type="submit" class="parcela-pill pendente border-0">
                                            <?= (int)$parcela['numero_parcela'] ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <form method="post" action="index.php?route=parcelamento_delete"
                                    onsubmit="return confirm('Excluir este parcelamento e todas as parcelas?')">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="id" value="<?= (int)$p['despesa_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($parcelamentos)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-5">
                                <i class="fa-solid fa-calendar-days fs-3 d-block mb-2 text-muted"></i>
                                Nenhum parcelamento cadastrado ainda.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal novo parcelamento -->
    <div class="modal fade" id="modalNovoParcelamento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="index.php?route=parcelamento_store">
                    <?= csrfCampo() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Novo parcelamento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-12">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="descricao" class="form-control" placeholder="Ex: Notebook"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Valor total</label>
                            <input type="number" step="0.01" min="0.01" name="valor_total" class="form-control"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nº de parcelas</label>
                            <input type="number" min="2" max="48" name="parcelas" class="form-control" value="2"
                                required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data da compra</label>
                            <input type="date" name="data" value="<?= date('Y-m-d') ?>" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cartão</label>
                            <select name="cartao_id" class="form-select" required>
                                <option value="">Selecione</option>
                                <?php foreach ($cartoes as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= $esc($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Categoria</label>
                            <select name="categoria_id" class="form-select">
                                <option value="">Outros</option>
                                <?php foreach ($categoriasDespesa as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= $esc($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar parcelamento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
