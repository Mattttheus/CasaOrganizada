// Objetivos e metas: cards com progresso, lançamentos (dinheiro ou compra no invest),
// vínculo de ativos, histórico e o resumo do painel.
import { db, demo, INVEST_URL, today } from '../contexto.js';
import { investidoNoObjetivo, progressoObjetivo, resumoObjetivos, totalPorPessoa } from '../dominio.js';
import { esc, money, dateBR } from '../helpers.js';
import { campo, field, form, select } from '../ui.js';
import { ativosInvest } from './investimentos.js';
import { layout } from './layout.js';

const CATEGORIAS_OBJETIVO = ['Casa', 'Carro', 'Viagem', 'Passeio', 'Reserva', 'Outro'];
const ICONES = { Casa: '🏠', Carro: '🚗', Viagem: '✈', Passeio: '🌳', Reserva: '🛟', Outro: '◎' };
const icone = item => ICONES[item.categoria] || '◎';

const progresso = item => progressoObjetivo(item, investidoNoObjetivo(item, db.vinculos, ativosInvest()));

function statusDaMeta(p) {
    if (!p.meta) return '<span class="badge gold">Defina a meta</span>';
    return p.pct >= 100 ? '<span class="badge">Meta alcançada!</span>' : `<span class="badge">${p.pct}%</span>`;
}

function planoDaMeta(item, p) {
    if (!p.meta) return 'Informe o valor da meta e o prazo para ver quanto guardar por mês.';
    if (p.pct >= 100) return 'Parabéns, o valor da meta já foi alcançado.';
    if (p.mensal === null) return `Faltam ${money(p.falta)}. Defina um prazo para ver quanto guardar por mês.`;
    return `Faltam ${money(p.falta)} · guarde <strong>${money(p.mensal)}/mês</strong> por ${p.meses} ${p.meses === 1 ? 'mês' : 'meses'} até ${dateBR(item.prazo)}.`;
}

function composicao(item, p) {
    const vinculos = db.vinculos.filter(v => v.objetivo_id === item.id);
    if (!vinculos.length) return '';
    const chips = vinculos.map(v => {
        const ativo = ativosInvest().find(a => a.ticker === v.ticker);
        const desvincular = db.user?.invest && !demo ? ` <button type="button" class="chip-x" data-vincular="${item.id}:${esc(v.ticker)}:0" title="Desvincular ${esc(v.ticker)}" aria-label="Desvincular ${esc(v.ticker)}">×</button>` : '';
        return `<span class="chip">▲ ${esc(v.ticker)} ${ativo ? money(ativo.posicao) : '—'}${desvincular}</span>`;
    }).join('');
    return `<div class="objetivo-composicao"><span>💵 ${money(p.dinheiro)} em dinheiro</span>${chips}</div>`;
}

function historico(item) {
    const lancamentos = db.aportes.filter(a => a.objetivo_id === item.id);
    if (!lancamentos.length) return '';
    const linhas = lancamentos.slice(0, 15).map(a => {
        const oQue = a.tipo === 'Investimento' ? `▲ ${esc(a.ticker)} · ${Number(a.quantidade).toLocaleString('pt-BR')} × ${money(a.preco)}` : esc(a.descricao || 'Dinheiro guardado');
        return `<tr><td>${dateBR(a.data)}</td><td>${oQue}</td><td>${money(a.valor)}</td><td class="muted">${a.investido_por ? 'por ' + esc(a.investido_por) : ''}</td></tr>`;
    }).join('');
    return `<details class="objetivo-editar"><summary>Histórico (${lancamentos.length})</summary><div class="table-wrap"><table class="tabela-compacta"><tbody>${linhas}</tbody></table></div></details>`;
}

function investir(item) {
    if (!db.user?.invest) return '';
    const livres = ativosInvest().filter(a => a.quantidade > 0 && !db.vinculos.some(v => v.ticker === a.ticker));
    const vincular = livres.length ? `<form data-form="vincular-objetivo" class="objetivo-form objetivo-vincular">
            <input type="hidden" name="objetivo_id" value="${esc(item.id)}">
            <select name="ticker" aria-label="Ativo que você já tem">${livres.map(a => `<option value="${esc(a.ticker)}">${esc(a.ticker)} · ${money(a.posicao)}</option>`).join('')}</select>
            <button class="btn" type="submit">Vincular ativo que já tenho</button>
        </form>` : '';
    return `<details class="objetivo-editar"><summary>▲ Investir pelo Projeto invest</summary>
        <form data-form="invest-objetivo" class="form-grid">
            <input type="hidden" name="objetivo_id" value="${esc(item.id)}">
            ${campo(item.id, 'Ativo (ticker)', 'ticker', 'text', 'required list="lista-ativos" maxlength="8" pattern="[A-Za-z0-9]{4,8}" placeholder="Ex: TAEE11" autocomplete="off" data-ticker-input')}
            ${campo(item.id, 'Quantidade de cotas', 'quantidade', 'number', 'required min="0.0001" step="any"')}
            ${campo(item.id, 'Preço por cota (R$)', 'preco', 'number', 'required min="0.01" step="0.01"')}
            ${campo(item.id, 'Data da compra', 'data', 'date', `value="${today}" max="${today}" required`)}
            ${campo(item.id, 'Custos (R$)', 'custos', 'number', 'min="0" step="0.01" placeholder="0,00"')}
            <div class="field"><label for="tipo_ativo-${item.id}">Tipo (só ticker novo)</label><select id="tipo_ativo-${item.id}" name="tipo_ativo"><option value="">—</option><option>AÇÃO</option><option>FII</option></select></div>
            <div class="field full"><small class="muted">Registra a compra no Projeto invest (mesmas regras do Cadastro) e vincula o ativo a este objetivo.</small><button class="btn btn-primary" type="submit">Comprar e vincular</button></div>
        </form>${vincular}
    </details>`;
}

function editar(item, p) {
    return `<details class="objetivo-editar"><summary>Meta, prazo e dinheiro guardado</summary>
        <form data-form="editar-objetivo" class="form-grid">
            <input type="hidden" name="id" value="${esc(item.id)}">
            ${campo(item.id, 'Valor da meta (R$)', 'valor_meta', 'number', `min="0.01" step="0.01" value="${p.meta || ''}" placeholder="Ex: 50000"`)}
            ${campo(item.id, 'Prazo', 'prazo', 'date', `value="${esc(item.prazo || '')}"`)}
            ${campo(item.id, 'Dinheiro guardado (R$)', 'valor_atual', 'number', `min="0" step="0.01" required value="${p.dinheiro}"`)}
            <div class="field full objetivo-acoes"><button class="btn btn-primary" type="submit">Salvar</button><button class="btn btn-danger" type="button" data-delete="objetivos:${item.id}">Excluir objetivo</button></div>
        </form>
    </details>`;
}

function objetivoCard(item) {
    const p = progresso(item);
    return `<article class="card objetivo">
        <div class="objetivo-topo"><span class="objetivo-icone">${icone(item)}</span><div><h3>${esc(item.nome)}</h3><small class="muted">${esc(item.categoria || 'Outro')}</small></div>${statusDaMeta(p)}</div>
        <div class="objetivo-valores"><strong>${money(p.atual)}</strong><span class="muted">${p.meta ? 'de ' + money(p.meta) : 'acumulados'}</span></div>
        <div class="limit"><i style="width:${p.pct}%"></i></div>
        ${composicao(item, p)}
        <p class="objetivo-plano">${planoDaMeta(item, p)}</p>
        <form data-form="aporte-objetivo" class="objetivo-form">
            <input type="hidden" name="objetivo_id" value="${esc(item.id)}">
            <input name="valor" type="number" min="0.01" step="0.01" required placeholder="Valor (R$)" aria-label="Valor a guardar em ${esc(item.nome)}">
            <button class="btn btn-primary" type="submit">Guardar</button>
        </form>
        ${investir(item)}${historico(item)}${editar(item, p)}
    </article>`;
}

function quemInvestiu() {
    const pessoas = totalPorPessoa(db.aportes);
    if (!pessoas.length) return '';
    return `<section class="card invest-card"><div class="card-head"><h2>Quem investiu</h2><span class="badge">${db.aportes.length} lançamentos</span></div><div class="card-body invest-grid">${pessoas.map(([nome, total]) => `<div><div class="metric-label">${esc(nome)}</div><div class="invest-value">${money(total)}</div></div>`).join('')}</div></section>`;
}

export function objetivos() {
    const lista = db.objetivos;
    const r = resumoObjetivos(lista.map(progresso));
    const novo = form(field('Objetivo', 'nome', 'text', 'required maxlength="100" placeholder="Ex: Reforma da cozinha"') + select('Categoria', 'categoria', CATEGORIAS_OBJETIVO) + field('Valor da meta (R$)', 'valor_meta', 'number', 'min="0.01" step="0.01" placeholder="Ex: 20000"') + field('Já guardado (R$)', 'valor_atual', 'number', 'min="0" step="0.01" value="0"') + field('Prazo', 'prazo', 'date'), 'add-objetivo', 'Novo objetivo');
    const aviso = db.user?.invest && db.invest?.erro ? `<div class="demo-banner">Não foi possível ler o Projeto invest: ${esc(db.invest.erro)}. Os valores dos ativos vinculados não entram na conta.</div>` : '';
    const metrica = (rotulo, valor) => `<div class="card metric"><div class="metric-label">${rotulo}</div><div class="metric-value">${valor}</div></div>`;
    return layout(`<div class="page-heading"><div><h1>Objetivos e metas</h1><p>Junte dinheiro e investimentos para os sonhos da família e acompanhe quanto falta.</p></div>${db.user?.invest ? `<div class="actions"><a class="btn" href="${INVEST_URL}">▲ Abrir investimentos</a></div>` : ''}</div>${aviso}
        <div class="grid-metrics">${metrica('Total acumulado', money(r.acumulado))}${metrica('Em investimentos', money(r.investido))}${metrica('Soma das metas', money(r.metas))}${metrica('Progresso médio', r.progressoMedio + '%')}</div>
        ${quemInvestiu()}
        ${lista.length ? `<div class="objetivos-grid">${lista.map(objetivoCard).join('')}</div>` : '<div class="card empty">Nenhum objetivo cadastrado ainda.</div>'}
        <datalist id="lista-ativos">${ativosInvest().map(a => `<option value="${esc(a.ticker)}">${esc(a.tipo || '')} · ${money(a.preco)}</option>`).join('')}</datalist>
        <div style="margin-top:18px">${novo}</div>`, 'Objetivos da família');
}

/** Resumo dos objetivos no painel inicial. */
export function objetivosResumo() {
    if (!db.objetivos.length) return '';
    const linhas = db.objetivos.map(item => {
        const p = progresso(item);
        return `<a class="objetivo-linha" href="#/objetivos"><span>${icone(item)} ${esc(item.nome)}</span><span class="muted">${money(p.atual)}${p.meta ? ' de ' + money(p.meta) : ''}</span><div class="limit"><i style="width:${p.pct}%"></i></div></a>`;
    }).join('');
    return `<section class="card invest-card"><div class="card-head"><h2>Objetivos e metas</h2><a class="badge" href="#/objetivos">Ver todos</a></div><div class="card-body objetivos-resumo">${linhas}</div></section>`;
}
