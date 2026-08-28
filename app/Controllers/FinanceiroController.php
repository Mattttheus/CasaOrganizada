<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/conexao.php';

final class FinanceiroController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db();
    }

    private function redirect(string $route = 'dashboard'): never
    {
        header('Location: index.php?route=' . urlencode($route));
        exit;
    }

    private function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    private function moneyToFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $value = trim((string)$value);

        // Aceita 1.234,56 e 1234.56.
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return round((float)$value, 2);
    }

    private function nullableInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int)$value;
    }

    private function validDate(mixed $value): string
    {
        $value = (string)$value;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException('Data inválida.');
        }

        return $value;
    }

    private function validId(mixed $value): int
    {
        $id = (int)$value;
        if ($id <= 0) {
            throw new InvalidArgumentException('Registro inválido.');
        }
        return $id;
    }

    public function data(): array
    {
        $month = max(1, min(12, (int)($_GET['mes'] ?? date('n'))));
        $year  = max(2000, min(2100, (int)($_GET['ano'] ?? date('Y'))));

        $inicio = sprintf('%04d-%02d-01', $year, $month);
        $fim = date('Y-m-t', strtotime($inicio));

        // Receitas: membro é opcional.
        $stmt = $this->pdo->prepare("
            SELECT
                r.id,
                r.descricao,
                r.valor,
                r.data_receita AS data,
                r.tipo,
                r.observacao,
                r.categoria_id,
                COALESCE(c.nome, 'Outros') AS categoria,
                r.membro_id,
                COALESCE(m.nome, 'Não informado') AS membro_nome
            FROM receitas r
            LEFT JOIN categorias c ON c.id = r.categoria_id
            LEFT JOIN membros_familia m ON m.id = r.membro_id
            WHERE r.data_receita BETWEEN :inicio AND :fim
            ORDER BY r.data_receita DESC, r.id DESC
        ");
        $stmt->execute(['inicio' => $inicio, 'fim' => $fim]);
        $receitas = $stmt->fetchAll();

        // Despesas: membro e cartão são opcionais.
        // valor_real é exposto também como valor_realizado para a View.
        $stmt = $this->pdo->prepare("
            SELECT
                d.id,
                d.descricao,
                COALESCE(c.nome, 'Outros') AS categoria,
                d.categoria_id,
                d.membro_id,
                COALESCE(m.nome, 'Não informado') AS membro_nome,
                d.cartao_id,
                ca.nome AS cartao_nome,
                d.tipo,
                d.forma_pagamento,
                d.valor_previsto,
                d.valor_real,
                d.valor_real AS valor_realizado,
                d.data_prevista,
                d.data_pagamento,
                COALESCE(d.data_pagamento, d.data_prevista) AS data,
                d.status,
                d.observacao
            FROM despesas d
            LEFT JOIN categorias c ON c.id = d.categoria_id
            LEFT JOIN membros_familia m ON m.id = d.membro_id
            LEFT JOIN cartoes ca ON ca.id = d.cartao_id
            WHERE COALESCE(d.data_pagamento, d.data_prevista) BETWEEN :inicio AND :fim
            ORDER BY COALESCE(d.data_pagamento, d.data_prevista) DESC, d.id DESC
        ");
        $stmt->execute(['inicio' => $inicio, 'fim' => $fim]);
        $despesas = $stmt->fetchAll();

        $categoriasReceita = $this->pdo
            ->query("SELECT id, nome FROM categorias WHERE tipo='receita' AND ativo=1 ORDER BY nome")
            ->fetchAll();

        $categoriasDespesa = $this->pdo
            ->query("SELECT id, nome FROM categorias WHERE tipo='despesa' AND ativo=1 ORDER BY nome")
            ->fetchAll();

        $cartoes = $this->pdo
            ->query("SELECT id, nome, limite_total FROM cartoes WHERE ativo=1 ORDER BY nome")
            ->fetchAll();

        $anotacoes = $this->pdo
            ->query("SELECT id, texto, data_agendamento FROM anotacoes ORDER BY data_agendamento ASC, id DESC LIMIT 30")
            ->fetchAll();

        $totalReceitas = array_sum(array_map(
            static fn(array $r): float => (float)$r['valor'],
            $receitas
        ));

        $totalDespesasPrevistas = array_sum(array_map(
            static fn(array $d): float => (float)$d['valor_previsto'],
            $despesas
        ));

        $totalDespesasRealizadas = array_sum(array_map(
            static fn(array $d): float => (float)$d['valor_real'],
            $despesas
        ));

        $despesasCategorias = [];
        foreach ($despesas as $d) {
            $cat = (string)$d['categoria'];
            $despesasCategorias[$cat] = ($despesasCategorias[$cat] ?? 0) + (float)$d['valor_real'];
        }
        arsort($despesasCategorias);

        $receitasCategorias = [];
        foreach ($receitas as $r) {
            $cat = (string)$r['categoria'];
            $receitasCategorias[$cat] = ($receitasCategorias[$cat] ?? 0) + (float)$r['valor'];
        }
        arsort($receitasCategorias);

        $individuais = 0.0;
        $coletivas = 0.0;
        foreach ($despesas as $d) {
            $valor = (float)$d['valor_real'];
            // O esquema original não possui tipo_grupo em despesas.
            // Sem coluna específica, o painel considera todas como coletivas.
            $coletivas += $valor;
        }

        return compact(
            'month',
            'year',
            'inicio',
            'fim',
            'receitas',
            'despesas',
            'categoriasReceita',
            'categoriasDespesa',
            'cartoes',
            'anotacoes',
            'totalReceitas',
            'totalDespesasPrevistas',
            'totalDespesasRealizadas',
            'despesasCategorias',
            'receitasCategorias',
            'individuais',
            'coletivas'
        );
    }

    public function storeReceita(): never
    {
        $descricao = trim((string)$this->post('descricao', ''));
        $valor = $this->moneyToFloat($this->post('valor'));
        $data = $this->validDate($this->post('data_receita', date('Y-m-d')));
        $categoriaId = $this->nullableInt($this->post('categoria_id'));
        $tipo = $this->post('tipo', 'Variavel');
        $observacao = trim((string)$this->post('observacao', ''));

        if ($descricao === '' || $valor <= 0) {
            throw new InvalidArgumentException('Descrição e valor da receita são obrigatórios.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO receitas
                (descricao, categoria_id, membro_id, valor, data_receita, tipo, observacao)
            VALUES
                (:descricao, :categoria_id, NULL, :valor, :data_receita, :tipo, :observacao)
        ");

        $stmt->execute([
            'descricao' => $descricao,
            'categoria_id' => $categoriaId,
            'valor' => $valor,
            'data_receita' => $data,
            'tipo' => $tipo === 'Fixa' ? 'Fixa' : 'Variavel',
            'observacao' => $observacao ?: null,
        ]);

        $this->redirect('receitas');
    }

    public function updateReceita(): never
    {
        $id = $this->validId($this->post('id'));
        $descricao = trim((string)$this->post('descricao', ''));
        $valor = $this->moneyToFloat($this->post('valor'));
        $data = $this->validDate($this->post('data_receita', date('Y-m-d')));
        $categoriaId = $this->nullableInt($this->post('categoria_id'));
        $tipo = $this->post('tipo', 'Variavel');
        $observacao = trim((string)$this->post('observacao', ''));

        if ($descricao === '' || $valor <= 0) {
            throw new InvalidArgumentException('Descrição e valor são obrigatórios.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE receitas
            SET descricao=:descricao,
                categoria_id=:categoria_id,
                membro_id=NULL,
                valor=:valor,
                data_receita=:data_receita,
                tipo=:tipo,
                observacao=:observacao
            WHERE id=:id
        ");

        $stmt->execute([
            'id' => $id,
            'descricao' => $descricao,
            'categoria_id' => $categoriaId,
            'valor' => $valor,
            'data_receita' => $data,
            'tipo' => $tipo === 'Fixa' ? 'Fixa' : 'Variavel',
            'observacao' => $observacao ?: null,
        ]);

        $this->redirect('receitas');
    }

    public function deleteReceita(): never
    {
        $id = $this->validId($this->post('id'));

        $stmt = $this->pdo->prepare("DELETE FROM receitas WHERE id=:id");
        $stmt->execute(['id' => $id]);

        $this->redirect('receitas');
    }

    public function storeDespesa(): never
    {
        $descricao = trim((string)$this->post('descricao', ''));
        $valor = $this->moneyToFloat($this->post('valor'));
        $data = $this->validDate($this->post('data', date('Y-m-d')));
        $categoriaId = $this->nullableInt($this->post('categoria_id'));
        $cartaoId = $this->nullableInt($this->post('cartao_id'));
        $formaPagamento = trim((string)$this->post('forma_pagamento', 'PIX'));
        $tipo = $this->post('tipo', 'Variavel');
        $observacao = trim((string)$this->post('observacao', ''));

        if ($descricao === '' || $valor <= 0) {
            throw new InvalidArgumentException('Descrição e valor da despesa são obrigatórios.');
        }

        $formas = ['PIX', 'Dinheiro', 'Cartao de Credito', 'Cartao de Debito', 'Boleto'];
        if (!in_array($formaPagamento, $formas, true)) {
            throw new InvalidArgumentException('Forma de pagamento inválida.');
        }

        if ($formaPagamento !== 'Cartao de Credito') {
            $cartaoId = null;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO despesas
                (descricao, categoria_id, membro_id, cartao_id, tipo, forma_pagamento,
                 valor_previsto, valor_real, data_prevista, data_pagamento, status, observacao)
            VALUES
                (:descricao, :categoria_id, NULL, :cartao_id, :tipo, :forma_pagamento,
                 :valor_previsto, :valor_real, :data_prevista, :data_pagamento, 'Pago', :observacao)
        ");

        $stmt->execute([
            'descricao' => $descricao,
            'categoria_id' => $categoriaId,
            'cartao_id' => $cartaoId,
            'tipo' => $tipo === 'Fixa' ? 'Fixa' : 'Variavel',
            'forma_pagamento' => $formaPagamento,
            'valor_previsto' => $valor,
            'valor_real' => $valor,
            'data_prevista' => $data,
            'data_pagamento' => $data,
            'observacao' => $observacao ?: null,
        ]);

        $this->redirect('despesas');
    }

    public function storeParcelamento(): never
    {
        $descricao = trim((string)$this->post('descricao', ''));
        $valorTotal = $this->moneyToFloat($this->post('valor_total'));
        $parcelas = max(2, min(48, (int)$this->post('parcelas', 2)));
        $data = $this->validDate($this->post('data', date('Y-m-d')));
        $categoriaId = $this->nullableInt($this->post('categoria_id'));
        $cartaoId = $this->nullableInt($this->post('cartao_id'));

        if ($descricao === '' || $valorTotal <= 0 || !$cartaoId) {
            throw new InvalidArgumentException('Descrição, valor e cartão são obrigatórios para parcelamento.');
        }

        $valorParcela = round($valorTotal / $parcelas, 2);

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO despesas
                    (descricao, categoria_id, membro_id, cartao_id, tipo, forma_pagamento,
                     valor_previsto, valor_real, data_prevista, data_pagamento, status, observacao)
                VALUES
                    (:descricao, :categoria_id, NULL, :cartao_id, 'Variavel', 'Cartao de Credito',
                     :valor_previsto, 0, :data_prevista, NULL, 'Previsto', :observacao)
            ");

            $stmt->execute([
                'descricao' => $descricao,
                'categoria_id' => $categoriaId,
                'cartao_id' => $cartaoId,
                'valor_previsto' => $valorTotal,
                'data_prevista' => $data,
                'observacao' => 'Compra parcelada',
            ]);

            $despesaId = (int)$this->pdo->lastInsertId();

            $stmtParcela = $this->pdo->prepare("
                INSERT INTO parcelas
                    (despesa_id, numero_parcela, total_parcelas, valor_parcela, data_vencimento, status)
                VALUES
                    (:despesa_id, :numero, :total, :valor, :vencimento, 'Pendente')
            ");

            for ($i = 1; $i <= $parcelas; $i++) {
                $vencimento = date('Y-m-d', strtotime("+".($i - 1)." month", strtotime($data)));

                // Ajusta centavos na última parcela para fechar exatamente o total.
                $valorAtual = ($i === $parcelas)
                    ? round($valorTotal - ($valorParcela * ($parcelas - 1)), 2)
                    : $valorParcela;

                $stmtParcela->execute([
                    'despesa_id' => $despesaId,
                    'numero' => $i,
                    'total' => $parcelas,
                    'valor' => $valorAtual,
                    'vencimento' => $vencimento,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->redirect('despesas');
    }

    public function updateDespesa(): never
    {
        $id = $this->validId($this->post('id'));
        $descricao = trim((string)$this->post('descricao', ''));
        $valor = $this->moneyToFloat($this->post('valor'));
        $categoriaId = $this->nullableInt($this->post('categoria_id'));
        $formaPagamento = trim((string)$this->post('forma_pagamento', 'PIX'));
        $data = $this->validDate($this->post('data', date('Y-m-d')));

        if ($descricao === '' || $valor <= 0) {
            throw new InvalidArgumentException('Descrição e valor são obrigatórios.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE despesas
            SET descricao=:descricao,
                categoria_id=:categoria_id,
                membro_id=NULL,
                forma_pagamento=:forma_pagamento,
                valor_previsto=:valor_previsto,
                valor_real=:valor_real,
                data_prevista=:data_prevista,
                data_pagamento=:data_pagamento,
                status='Pago'
            WHERE id=:id
        ");

        $stmt->execute([
            'id' => $id,
            'descricao' => $descricao,
            'categoria_id' => $categoriaId,
            'forma_pagamento' => $formaPagamento,
            'valor_previsto' => $valor,
            'valor_real' => $valor,
            'data_prevista' => $data,
            'data_pagamento' => $data,
        ]);

        $this->redirect('despesas');
    }

    public function deleteDespesa(): never
    {
        $id = $this->validId($this->post('id'));

        $stmt = $this->pdo->prepare("DELETE FROM despesas WHERE id=:id");
        $stmt->execute(['id' => $id]);

        $this->redirect('despesas');
    }

    public function storeLancamento(): never
    {
        $tipo = $this->post('tipo_transacao', 'despesa');

        if ($tipo === 'receita') {
            $_POST['data_receita'] = $_POST['data_receita'] ?? date('Y-m-d');
            $this->storeReceita();
        }

        $this->storeDespesa();
    }

    public function storeAnotacao(): never
    {
        $texto = trim((string)$this->post('texto', ''));
        $data = $this->validDate($this->post('data_agendamento', date('Y-m-d')));

        if ($texto === '') {
            throw new InvalidArgumentException('Digite uma anotação.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO anotacoes (texto, data_agendamento)
            VALUES (:texto, :data)
        ");
        $stmt->execute(['texto' => $texto, 'data' => $data]);

        $this->redirect('dashboard');
    }
}
