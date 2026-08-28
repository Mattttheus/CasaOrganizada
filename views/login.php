<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CasaOrganizada - Entrar</title>
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
                    <h1 class="h4 fw-bold mb-1">CasaOrganizada</h1>
                    <p class="text-muted mb-0">Acesse sua conta para continuar</p>
                </div>

                <?php if (!empty($erro)): ?>
                <div class="alert alert-danger py-2">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>

                <form action="index.php?route=login_store" method="post" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="voce@exemplo.com"
                            required autofocus>
                    </div>
                    <div class="mb-4">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" placeholder="••••••••"
                            required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>