<?php
// Objetivos e metas: lançamentos (dinheiro guardado ou compra no Projeto invest) e vínculo
// de ativos do invest. O valor de um objetivo = dinheiro guardado + posição dos ativos vinculados
// (calculado no front-end com a posição que o invest informa).
defined('CASA_API') || exit;

function listarAportes(): array
{
    $linhas = conexao()->query('SELECT a.id, a.objetivo_id, a.tipo, a.data, a.valor, a.descricao, a.ticker, a.quantidade, a.preco,
            u.nome AS investido_por
        FROM objetivo_aportes a LEFT JOIN usuarios u ON u.id = a.criado_por
        ORDER BY a.data DESC, a.criado_em DESC')->fetchAll();
    return array_map(fn($a) => ['valor' => (float)$a['valor'],
        'quantidade' => $a['quantidade'] === null ? null : (float)$a['quantidade'],
        'preco' => $a['preco'] === null ? null : (float)$a['preco']] + $a, $linhas);
}

function objetivoExistente($id): array
{
    $o = sql('SELECT * FROM objetivos WHERE id = ?', [uuidValido($id, 'Objetivo')])->fetch();
    if (!$o) falhar('Objetivo não encontrado.', 404);
    return $o;
}

/** Nome do objetivo que já tem o ticker, se for outro (cada ativo conta em um objetivo só). */
function objetivoComTicker(string $ticker, string $excetoId): ?string
{
    $nome = sql('SELECT o.nome FROM objetivo_ativos a JOIN objetivos o ON o.id = a.objetivo_id WHERE a.ticker = ? AND a.objetivo_id <> ?', [$ticker, $excetoId])->fetchColumn();
    return $nome === false ? null : $nome;
}

function vincularTicker(string $ticker, array $objetivo): void
{
    if ($outro = objetivoComTicker($ticker, $objetivo['id'])) falhar("$ticker já está vinculado ao objetivo \"$outro\". Desvincule lá primeiro.", 409);
    sql('INSERT IGNORE INTO objetivo_ativos (ticker, objetivo_id) VALUES (?, ?)', [$ticker, $objetivo['id']]);
}

/** POST objetivo_aporte {objetivo_id, tipo: Dinheiro|Investimento, data?, descricao?, ...} */
function acaoObjetivoAporte(): void
{
    $user = usuarioLogado();
    $body = corpo();
    $objetivo = objetivoExistente($body['objetivo_id'] ?? '');
    $data = data((string)(($body['data'] ?? '') ?: date('Y-m-d')), 'data', false);
    $descricao = texto(trim((string)($body['descricao'] ?? '')), ['max' => 150], 'descrição');
    $tipo = (string)($body['tipo'] ?? 'Dinheiro');
    if ($tipo === 'Dinheiro') guardarDinheiro($objetivo, $user, $data, $descricao, $body);
    if ($tipo === 'Investimento') comprarNoInvest($objetivo, $data, $descricao, $body);
    falhar('Tipo de lançamento inválido.', 422);
}

function guardarDinheiro(array $objetivo, array $user, string $data, string $descricao, array $body): void
{
    $valor = round(numeroPositivo($body['valor'] ?? null, 'o valor'), 2);
    $pdo = conexao();
    $pdo->beginTransaction();
    sql('INSERT INTO objetivo_aportes (id, objetivo_id, tipo, data, valor, descricao, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [uuid(), $objetivo['id'], 'Dinheiro', $data, $valor, $descricao ?: null, $user['id']]);
    sql('UPDATE objetivos SET valor_atual = valor_atual + ? WHERE id = ?', [$valor, $objetivo['id']]);
    $pdo->commit();
    responder(['ok' => true, 'resumo' => 'R$ ' . number_format($valor, 2, ',', '.') . " guardados em {$objetivo['nome']}."], 201);
}

/** Registra a compra no invest (mesmas regras do Cadastro: gestao.py --web-registrar) e vincula. */
function comprarNoInvest(array $objetivo, string $data, string $descricao, array $body): void
{
    $user = exigirInvest();
    $ticker = tickerValido($body['ticker'] ?? '');
    $quantidade = numeroPositivo($body['quantidade'] ?? null, 'a quantidade de cotas', 10000000);
    $preco = numeroPositivo($body['preco'] ?? null, 'o preço por cota', 1000000);
    $custos = ($body['custos'] ?? '') === '' ? 0.0 : $body['custos'];
    if (!is_numeric($custos) || (float)$custos < 0) falhar('Custos inválidos.', 422);
    $custos = (float)$custos;
    $tipoAtivo = mb_strtoupper(trim((string)($body['tipo_ativo'] ?? '')));
    if ($tipoAtivo !== '' && !in_array($tipoAtivo, ['AÇÃO', 'FII'], true)) falhar('Tipo do ativo deve ser AÇÃO ou FII.', 422);
    // confere o vínculo antes de comprar, para não registrar compra sem poder vincular
    if ($outro = objetivoComTicker($ticker, $objetivo['id'])) falhar("$ticker já está vinculado ao objetivo \"$outro\". Desvincule lá primeiro.", 409);

    set_time_limit(300);   // o invest recalcula a análise depois de gravar
    $lancamento = ['Data' => date('d/m/Y', strtotime($data)), 'Ticker' => $ticker, 'Operação' => 'COMPRA',
        'Quantidade de cotas' => $quantidade, 'Preço por cota (R$)' => $preco, 'Custos (R$)' => $custos];
    if ($tipoAtivo !== '') $lancamento['Tipo do ativo'] = $tipoAtivo;
    $r = chamarInvest('api.php', ['acao' => 'registrar', 'lancamentos' => [$lancamento]], 280);
    if (empty($r['ok'])) falhar('O invest recusou a compra: ' . implode(' · ', (array)($r['erros'] ?? ['erro desconhecido'])), 422);

    sql('INSERT INTO objetivo_aportes (id, objetivo_id, tipo, data, valor, descricao, ticker, quantidade, preco, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [uuid(), $objetivo['id'], 'Investimento', $data, round($quantidade * $preco + $custos, 2), $descricao ?: null, $ticker, $quantidade, $preco, $user['id']]);
    vincularTicker($ticker, $objetivo);
    responder(['ok' => true, 'resumo' => "Compra de $ticker registrada no invest e vinculada a {$objetivo['nome']}."], 201);
}

/** POST objetivo_vincular {objetivo_id, ticker, vincular: bool} */
function acaoObjetivoVincular(): void
{
    exigirInvest();
    $body = corpo();
    $objetivo = objetivoExistente($body['objetivo_id'] ?? '');
    $ticker = tickerValido($body['ticker'] ?? '');
    if (!empty($body['vincular'])) vincularTicker($ticker, $objetivo);
    else sql('DELETE FROM objetivo_ativos WHERE ticker = ? AND objetivo_id = ?', [$ticker, $objetivo['id']]);
    responder(['ok' => true]);
}
