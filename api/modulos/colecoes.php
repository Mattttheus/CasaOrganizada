<?php
// CRUD genérico das coleções da casa (receitas, despesas, cartões, ...).
// Tabelas e colunas vêm só desta lista (nunca do pedido), então o SQL montado é seguro.
defined('CASA_API') || exit;

// Colunas graváveis de cada coleção e suas regras (veja validarCampos). criado_por vem da sessão.
const COLECOES = [
    'membros' => ['tabela' => 'membros_familia', 'ordem' => 'nome', 'colunas' => [
        'nome' => ['s', 'obrig' => true, 'max' => 100],
        'parentesco' => ['s', 'max' => 50],
    ]],
    'cartoes' => ['tabela' => 'cartoes', 'ordem' => 'nome', 'colunas' => [
        'nome' => ['s', 'obrig' => true, 'max' => 100],
        'banco' => ['s', 'max' => 100],
        'limite' => ['f', 'min' => 0, 'maxv' => 99999999],
        'vencimento' => ['i', 'min' => 1, 'maxv' => 31],
        'responsavel' => ['s', 'max' => 100],
    ]],
    'receitas' => ['tabela' => 'receitas', 'ordem' => 'data DESC', 'colunas' => [
        'descricao' => ['s', 'obrig' => true, 'max' => 150],
        'categoria' => ['s', 'max' => 60],
        'valor' => ['f', 'obrig' => true, 'min' => 0.01, 'maxv' => 99999999],
        'data' => ['d', 'obrig' => true],
        'tipo' => ['s', 'opcoes' => ['Fixa', 'Variável']],
        'status' => ['s', 'opcoes' => ['Recebido', 'Previsto']],
        'observacao' => ['s', 'max' => 1000],
    ]],
    'despesas' => ['tabela' => 'despesas', 'ordem' => 'data DESC', 'colunas' => [
        'descricao' => ['s', 'obrig' => true, 'max' => 150],
        'categoria' => ['s', 'max' => 60],
        'valor' => ['f', 'obrig' => true, 'min' => 0.01, 'maxv' => 99999999],
        'data' => ['d', 'obrig' => true],
        'pagamento' => ['s', 'opcoes' => ['PIX', 'Dinheiro', 'Cartão de Débito', 'Cartão de Crédito', 'Boleto']],
        'gasto_por' => ['s', 'max' => 100],
        'tipo' => ['s', 'opcoes' => ['Fixa', 'Variável']],
        'status' => ['s', 'opcoes' => ['Pago', 'Previsto']],
        'observacao' => ['s', 'max' => 1000],
    ]],
    'parcelamentos' => ['tabela' => 'parcelamentos', 'ordem' => 'data DESC', 'colunas' => [
        'descricao' => ['s', 'obrig' => true, 'max' => 150],
        'valor_total' => ['f', 'obrig' => true, 'min' => 0.01, 'maxv' => 99999999],
        'parcelas' => ['i', 'obrig' => true, 'min' => 1, 'maxv' => 120],
        'pagas' => ['i', 'min' => 0, 'maxv' => 120],
        'data' => ['d', 'obrig' => true],
        'cartao' => ['s', 'max' => 100],
    ]],
    'notas' => ['tabela' => 'notas_tarefas', 'ordem' => 'data', 'colunas' => [
        'titulo' => ['s', 'obrig' => true, 'max' => 150],
        'descricao' => ['s', 'max' => 1000],
        'data' => ['d', 'obrig' => true],
        'tipo' => ['s', 'opcoes' => ['Tarefa', 'Nota']],
        'concluida' => ['b'],
    ]],
    'objetivos' => ['tabela' => 'objetivos', 'ordem' => 'criado_em, nome', 'colunas' => [
        'nome' => ['s', 'obrig' => true, 'max' => 100],
        'categoria' => ['s', 'opcoes' => ['Casa', 'Carro', 'Viagem', 'Passeio', 'Reserva', 'Outro']],
        'valor_meta' => ['f', 'min' => 0.01, 'maxv' => 9999999999],
        'valor_atual' => ['f', 'min' => 0, 'maxv' => 9999999999],
        'prazo' => ['d'],
    ]],
];

// Nomes amigáveis dos campos nas mensagens de erro.
const NOMES_CAMPOS = [
    'valor_total' => 'valor total', 'descricao' => 'descrição', 'observacao' => 'observação',
    'responsavel' => 'responsável', 'cartao' => 'cartão', 'titulo' => 'título',
    'valor_meta' => 'valor da meta', 'valor_atual' => 'valor guardado', 'gasto_por' => 'quem gastou',
];

function colecao(array $body): array
{
    $nome = (string)($body['colecao'] ?? '');
    if (!isset(COLECOES[$nome])) falhar('Coleção inválida.');
    return COLECOES[$nome] + ['nome' => $nome];
}

/** Converte os valores vindos do banco para os tipos do JSON. */
function formatarLinha(array $linha, array $colunas): array
{
    foreach ($colunas as $coluna => [$tipo]) {
        if (!isset($linha[$coluna])) continue;
        if ($tipo === 'f') $linha[$coluna] = (float)$linha[$coluna];
        if ($tipo === 'i') $linha[$coluna] = (int)$linha[$coluna];
        if ($tipo === 'b') $linha[$coluna] = (bool)$linha[$coluna];
    }
    return $linha;
}

/** Regras que envolvem mais de um campo (usa a linha atual nas atualizações). */
function validarConjunto(string $colecao, array $campos, array $atual = []): void
{
    if ($colecao === 'parcelamentos') {
        $parcelas = $campos['parcelas'] ?? $atual['parcelas'] ?? 1;
        $pagas = $campos['pagas'] ?? $atual['pagas'] ?? 0;
        if ($pagas > $parcelas) falhar('Parcelas pagas não podem passar do número de parcelas.', 422);
    }
}

function buscarLinha(array $def, string $id): array
{
    $linha = sql("SELECT * FROM {$def['tabela']} WHERE id = ?", [$id])->fetch();
    if (!$linha) falhar('Registro não encontrado.', 404);
    return formatarLinha($linha, $def['colunas']);
}

/** GET dados -> todas as coleções + lançamentos e vínculos dos objetivos + metas de gastos. */
function acaoDados(): void
{
    usuarioLogado();
    $resultado = [];
    foreach (COLECOES as $nome => $def) {
        $linhas = conexao()->query("SELECT * FROM {$def['tabela']} ORDER BY {$def['ordem']}")->fetchAll();
        $resultado[$nome] = array_map(fn($l) => formatarLinha($l, $def['colunas']), $linhas);
    }
    $resultado['aportes'] = listarAportes();
    $resultado['vinculos'] = conexao()->query('SELECT ticker, objetivo_id FROM objetivo_ativos ORDER BY ticker')->fetchAll();
    // nome de cada conta (id -> nome), para mostrar quem lançou despesas, receitas e notas
    $resultado['nomes'] = conexao()->query('SELECT id, nome FROM usuarios')->fetchAll(PDO::FETCH_KEY_PAIR);
    $resultado['metas'] = listarMetas();
    $resultado['meta_versoes'] = listarVersoesMetas();
    responder($resultado);
}

function acaoInserir(): void
{
    $user = usuarioLogado();
    $body = corpo();
    $def = colecao($body);
    $campos = validarCampos((array)($body['dados'] ?? []), $def['colunas'], true, NOMES_CAMPOS);
    validarConjunto($def['nome'], $campos);
    $campos['id'] = uuid();
    $campos['criado_por'] = $user['id'];
    $cols = array_keys($campos);
    sql(sprintf('INSERT INTO %s (%s) VALUES (%s)', $def['tabela'], implode(', ', $cols), implode(', ', array_fill(0, count($cols), '?'))), array_values($campos));
    responder(buscarLinha($def, $campos['id']), 201);
}

function acaoAtualizar(): void
{
    usuarioLogado();
    $body = corpo();
    $def = colecao($body);
    $id = uuidValido($body['id'] ?? '');
    $atual = buscarLinha($def, $id);
    $campos = validarCampos((array)($body['dados'] ?? []), $def['colunas'], false, NOMES_CAMPOS);
    if (!$campos) falhar('Nada para atualizar.');
    validarConjunto($def['nome'], $campos, $atual);
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
    sql("UPDATE {$def['tabela']} SET $sets WHERE id = ?", [...array_values($campos), $id]);
    responder(buscarLinha($def, $id));
}

function acaoExcluir(): void
{
    usuarioLogado();
    $body = corpo();
    $def = colecao($body);
    $stmt = sql("DELETE FROM {$def['tabela']} WHERE id = ?", [uuidValido($body['id'] ?? '')]);
    if (!$stmt->rowCount()) falhar('Registro não encontrado.', 404);
    responder(['ok' => true]);
}
