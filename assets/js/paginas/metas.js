// Metas de gastos: limites semanais, mensais e anuais × despesas reais, com histórico de
// períodos, histórico de alterações do limite (com notas) e o resumo do painel.
import { db, today } from '../contexto.js';
import { avaliarMeta, historicoMeta } from '../dominio.js';
import { esc, money, dateBR } from '../helpers.js';
import { campo, classeStatus, field, form, marcado, metrica, select } from '../ui.js';
import { layout } from './layout.js';

export const PERIODOS = ['Semanal', 'Mensal', 'Anual'];
export const CATEGORIAS_DESPESA = ['Todas', 'Moradia', 'Alimentação', 'Transporte', 'Lazer', 'Outros'];
const ROTULO_SITUACAO = { ok: 'Dentro da meta', atencao: 'Atenção', estourou: 'Meta estourada' };
const CLASSE_BADGE = { ok: '', atencao: 'gold', estourou: 'red' };
const NOME_PERIODO = { Semanal: 'esta semana', Mensal: 'este mês', Anual: 'este ano' };

const versoesDe = meta => db.meta_versoes.filter(v => v.meta_id === meta.id);
const avaliar = meta => avaliarMeta(meta, versoesDe(meta), db.despesas);
const barra = r => `<div class="limit"><i class="${r.situacao}" style="width:${Math.min(100, r.pct)}%"></i></div>`;

function selecionar(id, rotulo, nome, opcoes, atual) {
    return `<div class="field"><label for="${nome}-${id}">${rotulo}</label><select id="${nome}-${id}" name="${nome}">${opcoes.map(o => `<option ${o === atual ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select></div>`;
}

function detalheAtual(meta, r) {
    if (r.limite === null) return 'A meta ainda não estava valendo neste período.';
    const partes = [r.restante >= 0 ? `Ainda pode gastar <strong>${money(r.restante)}</strong> ${NOME_PERIODO[meta.periodo]}.` : `Passou <strong>${money(-r.restante)}</strong> do limite.`];
    if (r.porDia !== null && r.restante > 0) partes.push(`Dá <strong>${money(r.porDia)}/dia</strong> até ${dateBR(r.fim)}.`);
    if (r.projecao !== null) partes.push(`No ritmo atual, fecha ${NOME_PERIODO[meta.periodo]} em ${money(r.projecao)}${r.projecao > r.limite ? ' (acima da meta)' : ''}.`);
    if (r.previsto > 0) partes.push(`Mais ${money(r.previsto)} em despesas previstas.`);
    return partes.join(' ');
}

function historicoPeriodos(meta) {
    const linhas = historicoMeta(meta, versoesDe(meta), db.despesas).map(r => `<tr><td>${esc(r.rotulo)}</td><td>${money(r.gasto)}</td><td>${r.limite === null ? '—' : money(r.limite)}</td><td>${r.limite === null ? '' : `<span class="badge ${CLASSE_BADGE[r.situacao]}">${r.pct}%</span>`}</td></tr>`).join('');
    return `<details class="objetivo-editar"><summary>Histórico: real × meta</summary><div class="table-wrap"><table class="tabela-compacta"><thead><tr><th>Período</th><th>Gasto real</th><th>Meta</th><th></th></tr></thead><tbody>${linhas}</tbody></table></div></details>`;
}

function historicoAlteracoes(meta) {
    const versoes = [...versoesDe(meta)].reverse();
    const linhas = versoes.map(v => `<tr><td>desde ${dateBR(v.vigente_desde)}</td><td>${money(v.valor_limite)}</td><td>${esc(v.nota || '')}</td><td class="muted">${v.alterado_por ? 'por ' + esc(v.alterado_por) : ''}</td></tr>`).join('');
    return `<details class="objetivo-editar"><summary>Alterações da meta (${versoes.length})</summary><div class="table-wrap"><table class="tabela-compacta"><tbody>${linhas}</tbody></table></div></details>`;
}

function editar(meta, r) {
    const id = meta.id;
    return `<details class="objetivo-editar"><summary>Editar meta / novo limite</summary>
        <form data-form="meta-editar" class="form-grid">
            <input type="hidden" name="id" value="${esc(id)}">
            ${campo(id, 'Nome', 'nome', 'text', `required maxlength="100" value="${esc(meta.nome)}"`)}
            ${selecionar(id, 'Período', 'periodo', PERIODOS, meta.periodo)}
            ${selecionar(id, 'Categoria', 'categoria', CATEGORIAS_DESPESA, meta.categoria || 'Todas')}
            ${campo(id, 'Limite (R$)', 'valor_limite', 'number', `required min="0.01" step="0.01" value="${r.limite ?? ''}"`)}
            ${campo(id, 'Novo limite vale desde', 'vigente_desde', 'date', `value="${today}"`)}
            ${campo(id, 'Motivo da mudança', 'nota_alteracao', 'text', 'maxlength="500" placeholder="Ex: aluguel aumentou"')}
            <div class="field full"><label for="nota-${id}">Notas da meta</label><textarea id="nota-${id}" name="nota" maxlength="1000">${esc(meta.nota || '')}</textarea></div>
            <div class="field full checks">${marcado('Meta ativa', 'ativa', meta.ativa)}</div>
            <div class="field full objetivo-acoes"><button class="btn btn-primary" type="submit">Salvar</button><button class="btn btn-danger" type="button" data-excluir-meta="${esc(id)}" data-nome="${esc(meta.nome)}">Excluir meta</button></div>
            <small class="field full muted">Mudar o limite cria uma nova versão no histórico: os períodos anteriores continuam comparados com o limite que valia neles.</small>
        </form>
    </details>`;
}

function metaCard(meta) {
    const r = avaliar(meta);
    const categoria = meta.categoria || 'Todas as despesas';
    return `<article class="card objetivo meta-gasto ${meta.ativa ? '' : 'inativa'} ${classeStatus(r.limite === null ? 'neutro' : r.situacao)}">
        <div class="objetivo-topo"><span class="objetivo-icone">${{ Semanal: '7', Mensal: '🗓', Anual: '📅' }[meta.periodo] || '◔'}</span><div><h3>${esc(meta.nome)}</h3><small class="muted">${esc(meta.periodo)} · ${esc(categoria)}${meta.ativa ? '' : ' · pausada'}</small></div>${r.limite === null ? '' : `<span class="badge ${CLASSE_BADGE[r.situacao]}">${ROTULO_SITUACAO[r.situacao]}</span>`}</div>
        <div class="objetivo-valores"><strong>${money(r.gasto)}</strong><span class="muted">${r.limite === null ? 'sem limite vigente' : `de ${money(r.limite)} · ${r.pct}%`}</span></div>
        ${barra(r)}
        <p class="objetivo-plano">${detalheAtual(meta, r)}</p>
        ${meta.nota ? `<p class="meta-nota">📝 ${esc(meta.nota)}</p>` : ''}
        ${historicoPeriodos(meta)}${historicoAlteracoes(meta)}${editar(meta, r)}
    </article>`;
}

export function metas() {
    const lista = db.metas;
    const ativas = lista.filter(m => m.ativa).map(m => avaliar(m)).filter(r => r.limite !== null);
    const conta = s => ativas.filter(r => r.situacao === s).length;
    const novo = form(
        field('Nome da meta', 'nome', 'text', 'required maxlength="100" placeholder="Ex: Mercado do mês"') + select('Período', 'periodo', PERIODOS) +
        select('Categoria de despesa', 'categoria', CATEGORIAS_DESPESA) + field('Limite (R$)', 'valor_limite', 'number', 'required min="0.01" step="0.01"') +
        field('Vale desde', 'vigente_desde', 'date', `value="${today}"`) +
        '<div class="field full"><label for="nota">Notas</label><textarea id="nota" name="nota" maxlength="1000" placeholder="Ex: inclui feira e padaria"></textarea></div>',
        'meta-nova', 'Nova meta de gastos');
    return layout(`<div class="page-heading"><div><h1>Metas de gastos</h1><p>Limites semanais, mensais e anuais comparados com as despesas pagas de verdade.</p></div><div class="actions"><a class="btn" href="#/despesas">+ Despesa</a></div></div>
        <div class="grid-metrics">${metrica('Metas ativas', ativas.length, 'ok')}${metrica('Dentro da meta', conta('ok'), 'ok')}${metrica('Em atenção (80%+)', conta('atencao'), 'atencao')}${metrica('Estouradas', conta('estourou'), 'estourou')}</div>
        ${lista.length ? `<div class="objetivos-grid">${lista.map(metaCard).join('')}</div>` : '<div class="card empty">Nenhuma meta de gastos ainda. Crie a primeira abaixo.</div>'}
        <div style="margin-top:18px">${novo}</div>`, 'Metas de gastos');
}

/** Resumo das metas ativas no painel inicial. */
export function metasResumo() {
    const ativas = db.metas.filter(m => m.ativa);
    if (!ativas.length) return '';
    const linhas = ativas.map(meta => {
        const r = avaliar(meta);
        return `<a class="objetivo-linha" href="#/metas"><span>${esc(meta.nome)} <small class="muted">${esc(meta.periodo)}</small></span><span class="muted">${money(r.gasto)}${r.limite === null ? '' : ' de ' + money(r.limite)}</span>${barra(r)}</a>`;
    }).join('');
    return `<section class="card invest-card"><div class="card-head"><h2>Metas de gastos</h2><a class="badge" href="#/metas">Ver todas</a></div><div class="card-body objetivos-resumo">${linhas}</div></section>`;
}
