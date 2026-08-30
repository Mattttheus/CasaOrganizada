<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguranca.php';
exigirLogin();

/**
 * CasaOrganizada — Gestão de Cartões
 */

$esc = static fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn($v) => 'R$ ' . number_format((float)($v ?? 0), 2, ',', '.');

$cartoes = $cartoes ?? [];
$membros = $membros ?? [];
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CasaOrganizada | Cartões</title>

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

    .cartao-tile {
        border-radius: 16px;
        padding: 20px;
        color: #fff;
        background: linear-gradient(135deg, #172033 0%, #2563eb 100%);
        position: relative;
        min-height: 160px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .cartao-tile .limite-bar {
        height: 6px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .25);
        overflow: hidden;
    }

    .cartao-tile .limite-bar span {
        display: block;
        height: 100%;
        background: #fff;
    }

    .cartao-actions .btn {
        color: #fff;
        border-color: rgba(255, 255, 255, .4);
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
                <a class="nav-link active fw-semibold text-white" href="index.php?route=cartoes">Cartões</a>
                <a class="nav-link text-white-50" href="index.php?route=parcelamentos">Parcelamentos</a>
                <a class="nav-link text-white-50" href="index.php?route=membros">Família</a>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Cartões</h1>
                <p class="text-secondary mb-0">Cadastre os cartões da família e acompanhe limites utilizados.</p>
            </div>
            <button class="btn btn-primary rounded-3 px-3" type="button" data-bs-toggle="modal"
                data-bs-target="#modalNovoCartao">
                <i class="fa-solid fa-plus me-1"></i> Novo cartão
            </button>
        </div>

        <div class="row g-4">
            <?php foreach ($cartoes as $c): ?>
            <?php
            $limite = (float)($c['limite_total'] ?? 0);
            $gasto = (float)($c['total_gasto'] ?? 0);
            $percentual = $limite > 0 ? min(100, round(($gasto / $limite) * 100)) : 0;
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="cartao-tile shadow-sm">
                    <div>
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold fs-5"><?= $esc($c['nome']) ?></div>
                                <small class="text-white-50"><?= $esc($c['banco'] ?? 'Banco não informado') ?></small>
                            </div>
                            <i class="fa-solid fa-credit-card fs-4 text-white-50"></i>
                        </div>
                        <div class="mt-3">
                            <small class="text-white-50 d-block mb-1">
                                <?= $money($gasto) ?> de <?= $money($limite) ?>
                            </small>
                            <div class="limite-bar">
                                <span style="width: <?= $percentual ?>%"></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <small class="text-white-50">
                            <?= $esc($c['membro_nome'] ?? 'Sem responsável') ?>
                            <?php if (!empty($c['dia_vencimento'])): ?>
                            · vence dia <?= (int)$c['dia_vencimento'] ?>
                            <?php endif; ?>
                        </small>
                        <div class="cartao-actions d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-light rounded-circle"
                                data-bs-toggle="modal" data-bs-target="#modalEditarCartao<?= (int)$c['id'] ?>"
                                title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="post" action="index.php?route=cartao_delete"
                                onsubmit="return confirm('Excluir este cartão? As despesas vinculadas perderão a referência.')">
                                <?= csrfCampo() ?>
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-light rounded-circle" title="Excluir">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal editar cartão -->
            <div class="modal fade" id="modalEditarCartao<?= (int)$c['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" action="index.php?route=cartao_update">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <div class="modal-header">
                                <h5 class="modal-title">Editar cartão</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Nome</label>
                                    <input type="text" name="nome" class="form-control" value="<?= $esc($c['nome']) ?>"
                                        required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Banco</label>
                                    <input type="text" name="banco" class="form-control"
                                        value="<?= $esc($c['banco'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Limite total</label>
                                    <input type="number" step="0.01" min="0" name="limite_total" class="form-control"
                                        value="<?= $esc($c['limite_total']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Dia de fechamento</label>
                                    <input type="number" min="1" max="31" name="dia_fechamento" class="form-control"
                                        value="<?= $esc($c['dia_fechamento'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Dia de vencimento</label>
                                    <input type="number" min="1" max="31" name="dia_vencimento" class="form-control"
                                        value="<?= $esc($c['dia_vencimento'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Responsável</label>
                                    <select name="membro_id" class="form-select">
                                        <option value="">Sem responsável</option>
                                        <?php foreach ($membros as $m): ?>
                                        <option value="<?= (int)$m['id'] ?>"
                                            <?= (int)($c['membro_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>
                                            <?= $esc($m['nome']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar alterações</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($cartoes)): ?>
            <div class="col-12">
                <div class="cardx bodyx text-center text-secondary py-5">
                    <i class="fa-solid fa-credit-card fs-3 d-block mb-2 text-muted"></i>
                    Nenhum cartão cadastrado ainda.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal novo cartão -->
    <div class="modal fade" id="modalNovoCartao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="index.php?route=cartao_store">
                    <?= csrfCampo() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Novo cartão</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" placeholder="Ex: Nubank, Itaú"
                                required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Banco</label>
                            <input type="text" name="banco" class="form-control" placeholder="Ex: Nubank">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Limite total</label>
                            <input type="number" step="0.01" min="0" name="limite_total" class="form-control"
                                placeholder="0,00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dia de fechamento</label>
                            <input type="number" min="1" max="31" name="dia_fechamento" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dia de vencimento</label>
                            <input type="number" min="1" max="31" name="dia_vencimento" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Responsável</label>
                            <select name="membro_id" class="form-select">
                                <option value="">Sem responsável</option>
                                <?php foreach ($membros as $m): ?>
                                <option value="<?= (int)$m['id'] ?>"><?= $esc($m['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar cartão</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
