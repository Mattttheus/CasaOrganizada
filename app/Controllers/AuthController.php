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

    private const MAX_TENTATIVAS = 5;
    private const BLOQUEIO_MINUTOS = 15;

    public function showLogin(): void
    {
        if (self::usuarioLogado()) {
            header('Location: index.php?route=dashboard');
            exit;
        }

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
            'SELECT id, nome, email, senha, ativo, tentativas_login, bloqueado_ate FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && !empty($usuario['bloqueado_ate']) && strtotime((string)$usuario['bloqueado_ate']) > time()) {
            $_SESSION['login_erro'] = 'Conta temporariamente bloqueada por excesso de tentativas. Tente novamente mais tarde.';
            header('Location: index.php?route=login');
            return;
        }

        if (!$usuario || (int)$usuario['ativo'] !== 1 || !password_verify($senha, $usuario['senha'])) {
            if ($usuario) {
                $this->registrarTentativaFalha((int)$usuario['id'], (int)$usuario['tentativas_login']);
            }
            $_SESSION['login_erro'] = 'E-mail ou senha inválidos.';
            header('Location: index.php?route=login');
            return;
        }

        $this->limparTentativas((int)$usuario['id']);

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int)$usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];

        header('Location: index.php?route=dashboard');
    }

    private function registrarTentativaFalha(int $usuarioId, int $tentativasAtuais): void
    {
        $tentativas = $tentativasAtuais + 1;
        $bloqueadoAte = $tentativas >= self::MAX_TENTATIVAS
            ? date('Y-m-d H:i:s', time() + self::BLOQUEIO_MINUTOS * 60)
            : null;

        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET tentativas_login = :tentativas, bloqueado_ate = :bloqueado WHERE id = :id'
        );
        $stmt->execute(['tentativas' => $tentativas, 'bloqueado' => $bloqueadoAte, 'id' => $usuarioId]);
    }

    private function limparTentativas(int $usuarioId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios SET tentativas_login = 0, bloqueado_ate = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $usuarioId]);
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