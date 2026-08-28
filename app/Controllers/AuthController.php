<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/conexao.php';

/**
 * Controlador de autenticação.
 *
 * Usa a função db(): PDO definida em config/conexao.php
 * (mesmo padrão usado pelo restante do projeto).
 */
class AuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    public function showLogin(): void
    {
        $erro = $_SESSION['login_erro'] ?? null;
        unset($_SESSION['login_erro']);

        require __DIR__ . '/../../views/login.php';
    }

    public function login(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');

        if ($email === '' || $senha === '') {
            $_SESSION['login_erro'] = 'Informe e-mail e senha.';
            header('Location: index.php?route=login');
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, nome, email, senha_hash, ativo FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || (int)$usuario['ativo'] !== 1 || !password_verify($senha, $usuario['senha_hash'])) {
            $_SESSION['login_erro'] = 'E-mail ou senha inválidos.';
            header('Location: index.php?route=login');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int)$usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];

        header('Location: index.php?route=dashboard');
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        header('Location: index.php?route=login');
    }

    public static function usuarioLogado(): bool
    {
        return !empty($_SESSION['usuario_id']);
    }
}