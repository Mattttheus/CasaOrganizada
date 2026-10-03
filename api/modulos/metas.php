<?php
// Metas de gastos (limites diários, mensais e anuais). Cada mudança de limite vira uma nova
// versão (histórico com nota e autor); a comparação com as despesas é feita no front-end
// (assets/js/dominio.js), usando o limite que valia em cada período.
defined('CASA_API') || exit;

const PERIODOS_META = ['Diária', 'Mensal', 'Anual'];
const CATEGORIAS_DESPESA = ['Moradia', 'Alimentação', 'Transporte', 'Lazer', 'Outros'];

function listarMetas(): array
{
    $metas = sql('SELECT id, nome, periodo, categoria, nota, ativa, criado_em FROM metas_gastos ORDER BY ativa DESC, FIELD(periodo, ?, ?, ?), nome', PERIODOS_META)->fetchAll();
    return array_map(fn($m) => ['ativa' => (bool)$m['ativa']] + $m, $metas);
}

function listarVersoesMetas(): array
{
    $linhas = conexao()->query('SELECT v.id, v.meta_id, v.valor_limite, v.vigente_desde, v.nota, v.criado_em, u.nome AS alterado_por
        FROM meta_gasto_versoes v LEFT JOIN usuarios u ON u.id = v.criado_por
        ORDER BY v.meta_id, v.vigente_desde, v.criado_em')->fetchAll();
    return array_map(fn($v) => ['valor_limite' => (float)$v['valor_limite']] + $v, $linhas);
}

function metaExistente($id): array
{
    $m = sql('SELECT * FROM metas_gastos WHERE id = ?', [uuidValido($id, 'Meta')])->fetch();
    if (!$m) falhar('Meta não encontrada.', 404);
    return $m;
}

/** Limite vigente hoje (última versão com vigente_desde <= hoje; senão a primeira). */
function limiteAtual(string $metaId): ?float
{
    $v = sql('SELECT valor_limite FROM meta_gasto_versoes WHERE meta_id = ? ORDER BY vigente_desde <= CURDATE() DESC, vigente_desde DESC, criado_em DESC LIMIT 1', [$metaId])->fetchColumn();
    return $v === false ? null : (float)$v;
}

function camposDaMeta(array $body): array
{
    $categoria = trim((string)($body['categoria'] ?? ''));
    if ($categoria === 'Todas') $categoria = '';
    return [
        'nome' => texto(nomeValido($body['nome'] ?? ''), ['max' => 100], 'nome'),
        'periodo' => texto((string)($body['periodo'] ?? ''), ['opcoes' => PERIODOS_META], 'período'),
        'categoria' => $categoria === '' ? null : texto($categoria, ['opcoes' => CATEGORIAS_DESPESA], 'categoria'),
        'nota' => ($nota = texto(trim((string)($body['nota'] ?? '')), ['max' => 1000], 'nota')) === '' ? null : $nota,
    ];
}

function novaVersao(string $metaId, array $body, string $usuarioId): void
{
    $limite = round(numeroPositivo($body['valor_limite'] ?? null, 'o valor da meta', 99999999), 2);
    $desde = data((string)(($body['vigente_desde'] ?? '') ?: date('Y-m-d')), 'vigente desde');
    $nota = texto(trim((string)($body['nota_alteracao'] ?? '')), ['max' => 500], 'nota da alteração');
    sql('INSERT INTO meta_gasto_versoes (id, meta_id, valor_limite, vigente_desde, nota, criado_por) VALUES (?, ?, ?, ?, ?, ?)',
        [uuid(), $metaId, $limite, $desde, $nota === '' ? null : $nota, $usuarioId]);
}

/**
 * POST meta_salvar {id?, nome, periodo, categoria, nota, ativa?, valor_limite, vigente_desde?, nota_alteracao?}
 * Criação: grava a meta e a primeira versão do limite. Edição: atualiza os dados e, se o limite
 * mudou, acrescenta uma versão nova ao histórico (o limite antigo continua valendo para trás).
 */
function acaoMetaSalvar(): void
{
    $user = usuarioLogado();
    $body = corpo();
    $campos = camposDaMeta($body);
    $pdo = conexao();
    $pdo->beginTransaction();
    if (($body['id'] ?? '') === '') {
        $id = uuid();
        sql('INSERT INTO metas_gastos (id, nome, periodo, categoria, nota, criado_por) VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $campos['nome'], $campos['periodo'], $campos['categoria'], $campos['nota'], $user['id']]);
        novaVersao($id, $body + ['nota_alteracao' => 'Meta criada'], $user['id']);
        $pdo->commit();
        responder(['ok' => true, 'id' => $id, 'resumo' => "Meta \"{$campos['nome']}\" criada."], 201);
    }
    $meta = metaExistente($body['id']);
    if (!array_key_exists('nota', $body)) $campos['nota'] = $meta['nota'];   // nota não enviada: mantém a atual
    $campos['ativa'] = array_key_exists('ativa', $body) ? (empty($body['ativa']) ? 0 : 1) : (int)$meta['ativa'];
    sql('UPDATE metas_gastos SET nome = ?, periodo = ?, categoria = ?, nota = ?, ativa = ? WHERE id = ?',
        [$campos['nome'], $campos['periodo'], $campos['categoria'], $campos['nota'], $campos['ativa'], $meta['id']]);
    $mudouLimite = isset($body['valor_limite']) && $body['valor_limite'] !== '' && round((float)$body['valor_limite'], 2) !== limiteAtual($meta['id']);
    if ($mudouLimite) novaVersao($meta['id'], $body, $user['id']);
    $pdo->commit();
    responder(['ok' => true, 'resumo' => $mudouLimite ? "Novo limite de \"{$campos['nome']}\" registrado no histórico." : "Meta \"{$campos['nome']}\" atualizada."]);
}

/** POST meta_excluir {id} — apaga a meta e o histórico de limites dela (as despesas ficam). */
function acaoMetaExcluir(): void
{
    usuarioLogado();
    $meta = metaExistente(corpo()['id'] ?? '');
    sql('DELETE FROM metas_gastos WHERE id = ?', [$meta['id']]);
    responder(['ok' => true, 'resumo' => "Meta \"{$meta['nome']}\" excluída."]);
}
