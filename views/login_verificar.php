<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CasaOrganizada - Verificação em duas etapas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        background: linear-gradient(135deg, #0d6efd 0%, #084298 100%);
    }

    .login-card {
        max-width: 400px;
        width: 100%;
        margin: 0 auto;
    }
    </style>
</head>

<body>
    <div class="container">
        <div class="card login-card shadow border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <h1 class="h4 fw-bold mb-1">Verificação em duas etapas</h1>
                    <p class="text-muted mb-0">Enviamos um código de 6 dígitos para o seu e-mail.</p>
                </div>

                <?php if (!empty($erro)): ?>
                <div class="alert alert-danger py-2">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($info)): ?>
                <div class="alert alert-success py-2">
                    <?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <form action="index.php?route=login_verificar_store" method="post" novalidate>
                    <?= csrfCampo() ?>
                    <div class="mb-4">
                        <label for="codigo" class="form-label">Código de verificação</label>
                        <input type="text" class="form-control text-center fs-4 letter-spacing-2" id="codigo"
                            name="codigo" placeholder="000000" maxlength="6" inputmode="numeric" pattern="\d{6}"
                            required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Confirmar</button>
                </form>

                <form action="index.php?route=login_reenviar" method="post" class="mt-3 text-center">
                    <?= csrfCampo() ?>
                    <button type="submit" class="btn btn-link btn-sm">Reenviar código</button>
                </form>

                <div class="text-center mt-2">
                    <a href="index.php?route=login" class="small text-muted">Voltar ao login</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>