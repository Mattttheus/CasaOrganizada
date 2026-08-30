<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/conexao.php';

final class MembrosController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    private function redirect(string $route = 'membros'): void
    {
        header('Location: index.php?route=' . urlencode($route));
        exit;
    }

    private function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    private function validId(mixed $value): int
    {
        $id = (int)$value;
        if ($id <= 0) {
            throw new InvalidArgumentException('Registro invÃ¡lido.');
        }
        return $id;
    }

    public function data(): array
    {
        $membros = $this->pdo->query("
            SELECT m.id, m.nome, m.parentesco, m.ativo, m.usuario_id,
                   u.email AS usuario_email, u.role AS usuario_role
            FROM membros_familia m
            LEFT JOIN usuarios u ON u.id = m.usuario_id
            ORDER BY m.nome
        ")->fetchAll();

        $usuarios = $this->pdo->query("
            SELECT id, nome, email, celular, role, ativo, criado_em
            FROM usuarios
            ORDER BY nome
        ")->fetchAll();

        $usuariosDisponiveis = $this->pdo->query("
            SELECT id, nome FROM usuarios
            WHERE id NOT IN (
                SELECT usuario_id FROM membros_familia WHERE usuario_id IS NOT NULL
            )
            ORDER BY nome
        ")->fetchAll();

        return compact('membros', 'usuarios', 'usuariosDisponiveis');
    }

    public function storeMembro(): void
    {
        $nome = trim((string)$this->post('nome', ''));
        $parentesco = trim((string)$this->post('parentesco', ''));
        $usuarioIdBruto = $this->post('usuario_id');
        $usuarioId = ($usuarioIdBruto === '' || $usuarioIdBruto === null) ? null : (int)$usuarioIdBruto;

        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome do membro.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO membros_familia (nome, parentesco, usuario_id, ativo)
            VALUES (:nome, :parentesco, :usuario_id, 1)
        ");
        $stmt->execute([
            'nome' => $nome,
            'parentesco' => $parentesco ?: null,
            'usuario_id' => $usuarioId,
        ]);

        $this->redirect('membros');
    }

    public function updateMembro(): void
    {
        $id = $this->validId($this->post('id'));
        $nome = trim((string)$this->post('nome', ''));
        $parentesco = trim((string)$this->post('parentesco', ''));
        $usuarioIdBruto = $this->post('usuario_id');
        $usuarioId = ($usuarioIdBruto === '' || $usuarioIdBruto === null) ? null : (int)$usuarioIdBruto;
        $ativo = $this->post('ativo') ? 1 : 0;

        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome do membro.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE membros_familia
            SET nome=:nome, parentesco=:parentesco, usuario_id=:usuario_id, ativo=:ativo
            WHERE id=:id
        ");
        $stmt->execute([
            'id' => $id,
            'nome' => $nome,
            'parentesco' => $parentesco ?: null,
            'usuario_id' => $usuarioId,
            'ativo' => $ativo,
        ]);

        $this->redirect('membros');
    }

    public function deleteMembro(): void
    {
        $id = $this->validId($this->post('id'));

        $stmt = $this->pdo->prepare("DELETE FROM membros_familia WHERE id=:id");
        $stmt->execute(['id' => $id]);

        $this->redirect('membros');
    }

    public function storeUsuario(): void
    {
        $nome = trim((string)$this->post('nome', ''));
        $email = trim((string)$this->post('email', ''));
        $celular = trim((string)$this->post('celular', ''));
        $senha = (string)$this->post('senha', '');
        $role = $this->post('role', 'usuario') === 'admin' ? 'admin' : 'usuario';

        if ($nome === '' || $email === '' || $senha === '') {
            throw new InvalidArgumentException('Nome, e-mail e senha sÃ£o obrigatÃ³rios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail invÃ¡lido.');
        }

        if (strlen($senha) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }

        $existe = $this->pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
        $existe->execute(['email' => $email]);
        if ($existe->fetch()) {
            throw new InvalidArgumentException('JÃ¡ existe um usuÃ¡rio com este e-mail.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO usuarios (nome, email, celular, senha, role, ativo)
            VALUES (:nome, :email, :celular, :senha, :role, 1)
        ");
        $stmt->execute([
            'nome' => $nome,
            'email' => $email,
            'celular' => $celular ?: null,
            'senha' => password_hash($senha, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        $this->redirect('membros');
    }

    public function updateUsuario(): void
    {
        $id = $this->validId($this->post('id'));
        $nome = trim((string)$this->post('nome', ''));
        $email = trim((string)$this->post('email', ''));
        $celular = trim((string)$this->post('celular', ''));
        $senha = (string)$this->post('senha', '');
        $role = $this->post('role', 'usuario') === 'admin' ? 'admin' : 'usuario';
        $ativo = $this->post('ativo') ? 1 : 0;

        if ($nome === '' || $email === '') {
            throw new InvalidArgumentException('Nome e e-mail sÃ£o obrigatÃ³rios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail invÃ¡lido.');
        }

        $existe = $this->pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1');
        $existe->execute(['email' => $email, 'id' => $id]);
        if ($existe->fetch()) {
            throw new InvalidArgumentException('JÃ¡ existe outro usuÃ¡rio com este e-mail.');
        }

        if ($senha !== '') {
            if (strlen($senha) < 8) {
                throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
            }

            $stmt = $this->pdo->prepare("
                UPDATE usuarios
                SET nome=:nome, email=:email, celular=:celular, role=:role, ativo=:ativo,
                    senha=:senha, tentativas_login=0, bloqueado_ate=NULL
                WHERE id=:id
            ");
            $stmt->execute([
                'id' => $id,
                'nome' => $nome,
                'email' => $email,
                'celular' => $celular ?: null,
                'role' => $role,
                'ativo' => $ativo,
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE usuarios
                SET nome=:nome, email=:email, celular=:celular, role=:role, ativo=:ativo
                WHERE id=:id
            ");
            $stmt->execute([
                'id' => $id,
                'nome' => $nome,
                'email' => $email,
                'celular' => $celular ?: null,
                'role' => $role,
                'ativo' => $ativo,
            ]);
        }

        $this->redirect('membros');
    }

    public function deleteUsuario(): void
    {
        $id = $this->validId($this->post('id'));

        if (!empty($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === $id) {
            throw new InvalidArgumentException('VocÃª nÃ£o pode excluir o prÃ³prio usuÃ¡rio logado.');
        }

        $stmt = $this->pdo->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $this->redirect('membros');
    }
}
