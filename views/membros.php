<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/seguranca.php';
exigirLogin();

/**
 * CasaOrganizada — Membros da família e usuários do sistema
 */

$esc = static fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

$membros = $membros ?? [];
$usuarios = $usuarios ?? [];
$usuariosDisponiveis = $usuariosDisponiveis ?? [];

$erro = $_SESSION['acao_erro'] ?? null;
unset($_SESSION['acao_erro']);
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CasaOrganizada | Família</title>

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

    .avatar-circle {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
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
                <a class="nav-link text-white-50" href="index.php?route=parcelamentos">Parcelamentos</a>
                <a class="nav-link active fw-semibold text-white" href="index.php?route=membros">Família</a>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Membros da família</h1>
                <p class="text-secondary mb-0">Cadastre membros e gerencie os usuários com acesso ao sistema.</p>
            </div>
        </div>

        <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?= $esc($erro) ?></div>
        <?php endif; ?>

        <!-- ============================== MEMBROS ============================== -->
        <div class="cardx mb-4">
            <div class="headerx">
                <strong class="text-dark"><i class="fa-solid fa-users text-primary me-2"></i>Membros</strong>
                <button class="btn btn-sm btn-primary rounded-3 px-3" type="button" data-bs-toggle="modal"
                    data-bs-target="#modalNovoMembro">
                    <i class="fa-solid fa-plus me-1"></i> Novo membro
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Parentesco</th>
                            <th>Usuário vinculado</th>
                            <th>Status</th>
                            <th class="text-center" style="width: 90px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membros as $m): ?>
                        <tr>
                            <td class="d-flex align-items-center gap-2">
                                <span class="avatar-circle"><?= $esc(mb_strtoupper(mb_substr($m['nome'], 0, 1))) ?></span>
                                <span class="fw-semibold text-dark"><?= $esc($m['nome']) ?></span>
                            </td>
                            <td><?= $esc($m['parentesco'] ?? 'Não informado') ?></td>
                            <td>
                                <?php if (!empty($m['usuario_email'])): ?>
                                <?= $esc($m['usuario_email']) ?>
                                <span class="badge bg-light text-dark border text-uppercase"><?= $esc($m['usuario_role']) ?></span>
                                <?php else: ?>
                                <span class="text-muted">Sem acesso ao sistema</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= (int)$m['ativo'] === 1 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
                                    <?= (int)$m['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary border-0 rounded-circle"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarMembro<?= (int)$m['id'] ?>"
                                        title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="post" action="index.php?route=membro_delete"
                                        onsubmit="return confirm('Excluir este membro da família?')">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="Excluir">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal editar membro -->
                        <div class="modal fade" id="modalEditarMembro<?= (int)$m['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post" action="index.php?route=membro_update">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Editar membro</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body row g-3">
                                            <div class="col-md-8">
                                                <label class="form-label">Nome</label>
                                                <input type="text" name="nome" class="form-control"
                                                    value="<?= $esc($m['nome']) ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Parentesco</label>
                                                <input type="text" name="parentesco" class="form-control"
                                                    value="<?= $esc($m['parentesco'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Usuário vinculado</label>
                                                <select name="usuario_id" class="form-select">
                                                    <option value="">Sem acesso ao sistema</option>
                                                    <?php if (!empty($m['usuario_id'])): ?>
                                                    <option value="<?= (int)$m['usuario_id'] ?>" selected>
                                                        <?= $esc($m['usuario_email']) ?>
                                                    </option>
                                                    <?php endif; ?>
                                                    <?php foreach ($usuariosDisponiveis as $u): ?>
                                                    <option value="<?= (int)$u['id'] ?>"><?= $esc($u['nome']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" name="ativo"
                                                        id="ativoMembro<?= (int)$m['id'] ?>" value="1"
                                                        <?= (int)$m['ativo'] === 1 ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="ativoMembro<?= (int)$m['id'] ?>">
                                                        Membro ativo
                                                    </label>
                                                </div>
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

                        <?php if (empty($membros)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-5">
                                <i class="fa-solid fa-users fs-3 d-block mb-2 text-muted"></i>
                                Nenhum membro cadastrado ainda.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================== USUÁRIOS ============================== -->
        <div class="cardx">
            <div class="headerx">
                <strong class="text-dark"><i class="fa-solid fa-user-shield text-primary me-2"></i>Usuários do sistema</strong>
                <button class="btn btn-sm btn-primary rounded-3 px-3" type="button" data-bs-toggle="modal"
                    data-bs-target="#modalNovoUsuario">
                    <i class="fa-solid fa-plus me-1"></i> Novo usuário
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Celular</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th class="text-center" style="width: 90px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?= $esc($u['nome']) ?></td>
                            <td><?= $esc($u['email']) ?></td>
                            <td><?= $esc($u['celular'] ?? 'Não informado') ?></td>
                            <td><span class="badge bg-light text-dark border text-uppercase"><?= $esc($u['role']) ?></span></td>
                            <td>
                                <span class="badge <?= (int)$u['ativo'] === 1 ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
                                    <?= (int)$u['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary border-0 rounded-circle"
                                        data-bs-toggle="modal" data-bs-target="#modalEditarUsuario<?= (int)$u['id'] ?>"
                                        title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <?php if ((int)($_SESSION['usuario_id'] ?? 0) !== (int)$u['id']): ?>
                                    <form method="post" action="index.php?route=usuario_delete"
                                        onsubmit="return confirm('Excluir este usuário do sistema?')">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="Excluir">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal editar usuário -->
                        <div class="modal fade" id="modalEditarUsuario<?= (int)$u['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post" action="index.php?route=usuario_update">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Editar usuário</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Nome</label>
                                                <input type="text" name="nome" class="form-control"
                                                    value="<?= $esc($u['nome']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">E-mail</label>
                                                <input type="email" name="email" class="form-control"
                                                    value="<?= $esc($u['email']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Celular</label>
                                                <input type="text" name="celular" class="form-control"
                                                    value="<?= $esc($u['celular'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Perfil</label>
                                                <select name="role" class="form-select">
                                                    <option value="usuario" <?= $u['role'] === 'usuario' ? 'selected' : '' ?>>Usuário</option>
                                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Nova senha</label>
                                                <input type="password" name="senha" class="form-control"
                                                    placeholder="Deixe em branco para manter" minlength="8"
                                                    autocomplete="new-password">
                                            </div>
                                            <div class="col-md-6 d-flex align-items-end">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" name="ativo"
                                                        id="ativoUsuario<?= (int)$u['id'] ?>" value="1"
                                                        <?= (int)$u['ativo'] === 1 ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="ativoUsuario<?= (int)$u['id'] ?>">
                                                        Usuário ativo (permite login)
                                                    </label>
                                                </div>
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

                        <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-5">
                                <i class="fa-solid fa-user-shield fs-3 d-block mb-2 text-muted"></i>
                                Nenhum usuário cadastrado ainda.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal novo membro -->
    <div class="modal fade" id="modalNovoMembro" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="index.php?route=membro_store">
                    <?= csrfCampo() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Novo membro</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Parentesco</label>
                            <input type="text" name="parentesco" class="form-control" placeholder="Ex: Filho(a)">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Usuário vinculado</label>
                            <select name="usuario_id" class="form-select">
                                <option value="">Sem acesso ao sistema</option>
                                <?php foreach ($usuariosDisponiveis as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= $esc($u['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar membro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal novo usuário -->
    <div class="modal fade" id="modalNovoUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="index.php?route=usuario_store">
                    <?= csrfCampo() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Novo usuário</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Celular</label>
                            <input type="text" name="celular" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Perfil</label>
                            <select name="role" class="form-select">
                                <option value="usuario">Usuário</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Senha</label>
                            <input type="password" name="senha" class="form-control" minlength="8" required
                                autocomplete="new-password">
                            <small class="text-muted">Mínimo de 8 caracteres.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
