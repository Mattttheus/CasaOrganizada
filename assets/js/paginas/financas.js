// Páginas do dia a dia: visão geral, receitas, despesas, cartões, parcelamentos e calendário.
import { calendarContent, despesaStatus, receitaStatus } from '../calendar.js';
import { db, estado, INVEST_URL, today } from '../contexto.js';
import { avaliarMeta, maisRecentes, periodoDe, quemGastou, resumoGastos, somaValores } from '../dominio.js';
import { esc, money, dateBR } from '../helpers.js';
import { field, form, select } from '../ui.js';
import { investCard } from './investimentos.js';
import { layout } from './layout.js';
import { metasResumo } from './metas.js';
import { objetivosResumo } from './objetivos.js';

const CATEGORIAS = ['Moradia', 'Alimentação', 'Transporte', 'Lazer', 'Salário', 'Outros'];
const observacao = '<div class="field full"><label for="observacao">Observação</label><textarea id="observacao" name="observacao" maxlength="1000"></textarea></div>';

const metrica = (rotulo, valor) => `<div class="card metric"><div class="metric-label">${rotulo}</div><div class="metric-value">${valor}</div></div>`;

export function dashboard() {
    const receitas = somaValores(db.receitas), despesas = somaValores(db.despesas), saldo = receitas - despesas;
    const recentes = maisRecentes(db.despesas);
    const max = Math.max(receitas, despesas, 1);
    const acoes = `<a class="btn btn-primary" href="#/receitas">+ Receita</a><a class="btn" href="#/despesas">+ Despesa</a>${db.user?.invest ? `<a class="btn" href="${INVEST_URL}">▲ Investimentos</a>` : ''}`;
    const fluxo = `<section class="card"><div class="card-head"><h2>Fluxo do período</h2><span class="badge">Atual</span></div><div class="card-body"><div class="chart-bars"><div class="bar" style="height:${Math.max(receitas / max * 100, 4)}%"><label>Receitas</label></div><div class="bar expense" style="height:${Math.max(despesas / max * 100, 4)}%"><label>Despesas</label></div></div><div class="legend"><span><i class="dot"></i>Entradas</span><span><i class="dot red"></i>Saídas</span></div></div></section>`;
    const ultimas = `<section class="card"><div class="card-head"><h2>Últimas despesas</h2><a href="#/despesas" class="badge">Ver todas</a></div>${recentes.length ? `<div class="table-wrap"><table><tbody>${recentes.map(item => `<tr><td>${dateBR(item.data)}<br><strong>${esc(item.descricao)}</strong></td><td>${money(item.valor)}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Nenhuma despesa registrada.</div>'}</section>`;
    return layout(`<div class="page-heading"><div><h1>Visão geral</h1><p>Um retrato claro do dinheiro da sua família.</p></div><div class="actions">${acoes}</div></div>
        <div class="grid-metrics">${metrica('Receitas', money(receitas))}${metrica('Despesas', money(despesas))}${metrica('Saldo', money(saldo))}${metrica('Itens cadastrados', db.receitas.length + db.despesas.length)}</div>
        ${metasResumo()}${investCard()}${objetivosResumo()}<div class="two-col">${fluxo}${ultimas}</div>`, 'Dashboard financeiro');
}

export function receitas() {
    const novo = form(field('Descrição', 'descricao', 'text', 'required maxlength="150" placeholder="Ex: Salário, Freelance"') + select('Categoria', 'categoria', CATEGORIAS.slice(4)) + field('Valor', 'valor', 'number', 'required min="0.01" step="0.01"') + field('Data', 'data', 'date', `value="${today}" required`) + select('Tipo', 'tipo', ['Variável', 'Fixa']) + select('Status', 'status', ['Recebido', 'Previsto']) + observacao, 'add-receita', 'Nova receita');
    const linhas = db.receitas.map(item => {
        const status = receitaStatus(item);
        return `<tr><td>${dateBR(item.data)}</td><td><strong>${esc(item.descricao)}</strong></td><td>${esc(item.categoria)}</td><td>${money(item.valor)}</td><td><span class="badge ${status.cls}">${status.label}</span></td><td>${item.status === 'Previsto' ? `<button class="btn" data-toggle="receitas:${item.id}:status:Recebido">Recebido</button> ` : ''}<button class="btn btn-danger" data-delete="receitas:${item.id}">Excluir</button></td></tr>`;
    }).join('');
    return layout(`<div class="page-heading"><div><h1>Receitas</h1><p>Gerencie todas as entradas financeiras.</p></div></div>${novo}<section class="card" style="margin-top:18px"><div class="card-head"><h2>Receitas lançadas</h2><span class="badge">${db.receitas.length} itens</span></div>${db.receitas.length ? `<div class="table-wrap"><table><thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Status</th><th></th></tr></thead><tbody>${linhas}</tbody></table></div>` : '<div class="empty">Nenhuma receita registrada.</div>'}</section>`, 'Entradas financeiras');
}

// Pessoas que podem ter gastado: contas do sistema e membros da família (sem repetir).
const pessoas = () => [...new Set([db.user?.name, ...Object.values(db.nomes), ...db.membros.map(m => m.nome)].filter(Boolean))];

const ROTULOS_PERIODO = { 'Diária': 'Hoje', Mensal: 'Este mês', Anual: 'Este ano' };
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/** Meta geral (todas as despesas, ativa) do período, para comparar com o gasto real. */
function metaGeral(periodo) {
    const meta = db.metas.find(m => m.ativa && !m.categoria && m.periodo === periodo);
    return meta ? avaliarMeta(meta, db.meta_versoes.filter(v => v.meta_id === meta.id), db.despesas) : null;
}

function cartaoPeriodo(periodo) {
    const p = periodoDe(periodo);
    const r = resumoGastos(db.despesas, p.inicio, p.fim, db.nomes);
    const meta = metaGeral(periodo);
    const comparacao = meta?.limite ? `<small class="muted">de ${money(meta.limite)} · ${meta.pct}%</small><div class="limit"><i class="${meta.situacao}" style="width:${Math.min(100, meta.pct)}%"></i></div>` : '<small class="muted">sem meta geral · <a href="#/metas">criar</a></small>';
    const ativo = estado.periodoGastos === periodo ? 'ativo' : '';
    return `<button type="button" class="card metric periodo-gastos ${ativo}" data-periodo-gastos="${periodo}"><div class="metric-label">Gasto ${ROTULOS_PERIODO[periodo].toLowerCase()}</div><div class="metric-value">${money(r.total)}</div>${comparacao}</button>`;
}

const listaValores = (itens, vazio) => itens.length
    ? `<div class="table-wrap"><table class="tabela-compacta"><tbody>${itens.map(([nome, valor]) => `<tr><td>${esc(nome)}</td><td style="text-align:right">${money(valor)}</td></tr>`).join('')}</tbody></table></div>`
    : `<div class="empty">${vazio}</div>`;

function relatorioGastos() {
    const periodo = estado.periodoGastos;
    const p = periodoDe(periodo);
    const anual = periodo === 'Anual';
    const r = resumoGastos(db.despesas, p.inicio, p.fim, db.nomes, anual);
    const rotulo = chave => anual ? `${MESES[Number(chave.slice(5, 7)) - 1]}/${chave.slice(0, 4)}` : dateBR(chave);
    const linhas = r.linhaDoTempo.map(ponto => `<tr><td>${anual ? rotulo(ponto.chave) : `<a href="#/calendario" data-ir-dia="${ponto.chave}" title="Ver o dia no calendário">${rotulo(ponto.chave)}</a>`}</td><td>${ponto.itens}</td><td>${ponto.pessoas.map(([nome, valor]) => `${esc(nome)} ${money(valor)}`).join(' · ')}</td><td style="text-align:right"><strong>${money(ponto.total)}</strong></td></tr>`).join('');
    const titulo = `${ROTULOS_PERIODO[periodo]} · ${dateBR(p.inicio)}${p.inicio === p.fim ? '' : ' a ' + dateBR(p.fim)}`;
    return `<section class="card" style="margin-bottom:18px"><div class="card-head"><h2>Relatório de gastos — ${titulo}</h2><span class="badge">${r.quantidade} despesas pagas${r.previsto ? ` · ${money(r.previsto)} previsto` : ''}</span></div>
        <div class="card-body relatorio-gastos">
            <div><h3>Quem gastou</h3>${listaValores(r.porPessoa, 'Nenhum gasto no período.')}</div>
            <div><h3>Por categoria</h3>${listaValores(r.porCategoria, 'Nenhum gasto no período.')}</div>
        </div>
        ${r.linhaDoTempo.length ? `<div class="table-wrap"><table><thead><tr><th>${anual ? 'Mês' : 'Dia'}</th><th>Despesas</th><th>Quem gastou</th><th style="text-align:right">Total</th></tr></thead><tbody>${linhas}</tbody></table></div>` : ''}
    </section>`;
}

export function despesas() {
    const quem = `<div class="field"><label for="gasto_por">Quem gastou</label><select id="gasto_por" name="gasto_por">${pessoas().map(n => `<option ${n === db.user?.name ? 'selected' : ''}>${esc(n)}</option>`).join('')}</select></div>`;
    const novo = form(field('Descrição', 'descricao', 'text', 'required maxlength="150" placeholder="Ex: Mercado, Luz, Internet"') + field('Valor', 'valor', 'number', 'required min="0.01" step="0.01"') + field('Data', 'data', 'date', `value="${today}" required`) + quem + select('Categoria', 'categoria', CATEGORIAS.slice(0, 4).concat('Outros')) + select('Forma de pagamento', 'pagamento', ['PIX', 'Dinheiro', 'Cartão de Débito', 'Cartão de Crédito', 'Boleto']) + select('Tipo', 'tipo', ['Variável', 'Fixa']) + select('Status', 'status', ['Pago', 'Previsto']) + observacao, 'add-despesa', 'Nova despesa');
    const linhas = db.despesas.map(item => {
        const status = despesaStatus(item);
        return `<tr><td><a href="#/calendario" data-ir-dia="${esc(item.data)}" title="Ver o dia no calendário">${dateBR(item.data)}</a></td><td><strong>${esc(item.descricao)}</strong><br><small>${esc(item.categoria)}</small></td><td>${esc(quemGastou(item, db.nomes))}</td><td>${esc(item.pagamento)}</td><td><span class="badge ${status.cls}">${status.label}</span></td><td>${money(item.valor)}</td><td>${item.status === 'Previsto' ? `<button class="btn" data-toggle="despesas:${item.id}:status:Pago">Pago</button> ` : ''}<button class="btn btn-danger" data-delete="despesas:${item.id}">Excluir</button></td></tr>`;
    }).join('');
    return layout(`<div class="page-heading"><div><h1>Despesas</h1><p>Quanto foi gasto, em que dia e por quem — no dia, no mês e no ano.</p></div><div class="actions"><a class="btn" href="#/metas">◔ Metas de gastos</a><a class="btn" href="#/calendario">🗓 Calendário</a></div></div>
        <div class="grid-metrics periodos">${['Diária', 'Mensal', 'Anual'].map(cartaoPeriodo).join('')}<div class="card metric"><div class="metric-label">Total lançado</div><div class="metric-value">${db.despesas.length}</div><small class="muted">despesas no histórico</small></div></div>
        ${relatorioGastos()}
        ${novo}<section class="card" style="margin-top:18px"><div class="card-head"><h2>Histórico</h2><span class="badge red">${db.despesas.length} itens</span></div>${db.despesas.length ? `<div class="table-wrap"><table><thead><tr><th>Data</th><th>Descrição</th><th>Quem gastou</th><th>Pagamento</th><th>Status</th><th>Valor</th><th></th></tr></thead><tbody>${linhas}</tbody></table></div>` : '<div class="empty">Nenhuma despesa registrada.</div>'}</section>`, 'Saídas financeiras');
}

export function cartoes() {
    const gastoNoCredito = somaValores(db.despesas.filter(item => item.pagamento === 'Cartão de Crédito'));
    const lista = db.cartoes.length ? db.cartoes.map(card => {
        const percent = Math.min(gastoNoCredito / Number(card.limite || 1) * 100, 100);
        return `<article class="card card-tile"><h3>${esc(card.nome)}</h3><p>${esc(card.banco || 'Banco não informado')}</p><strong>${money(gastoNoCredito)}</strong> de ${money(card.limite)}<div class="limit"><i style="width:${percent}%"></i></div><small>Vencimento dia ${esc(card.vencimento)} · ${esc(card.responsavel)}</small><div style="margin-top:18px"><button class="btn btn-danger" data-delete="cartoes:${card.id}">Excluir</button></div></article>`;
    }).join('') : '<div class="card empty">Nenhum cartão cadastrado ainda.</div>';
    const novo = form(field('Nome', 'nome', 'text', 'required maxlength="100" placeholder="Ex: Cartão principal"') + field('Banco', 'banco', 'text', 'maxlength="100"') + field('Limite total', 'limite', 'number', 'min="0" step="0.01"') + field('Dia de vencimento', 'vencimento', 'number', 'min="1" max="31" value="10"') + field('Responsável', 'responsavel', 'text', 'maxlength="100" placeholder="Quem usa este cartão"'), 'add-cartao', 'Novo cartão');
    return layout(`<div class="page-heading"><div><h1>Cartões</h1><p>Acompanhe limites e gastos da família.</p></div></div><div class="cards-grid">${lista}</div><div style="margin-top:18px">${novo}</div>`, 'Cartões da família');
}

export function parcelamentos() {
    const novo = form(field('Compra', 'descricao', 'text', 'required maxlength="150" placeholder="Ex: Notebook"') + field('Valor total', 'valorTotal', 'number', 'required min="0.01" step="0.01"') + field('Número de parcelas', 'parcelas', 'number', 'required min="2" max="120" value="2"') + field('Data da compra', 'data', 'date', `value="${today}" required`) + select('Cartão', 'cartao', db.cartoes.length ? db.cartoes.map(item => item.nome) : ['Nenhum cartão']), 'add-parcelamento', 'Novo parcelamento');
    const linhas = db.parcelamentos.map(item => `<tr><td><strong>${esc(item.descricao)}</strong><br><small>${Number(item.parcelas)} parcelas</small></td><td>${esc(item.cartao)}</td><td>${money(item.valor_total)}</td><td>${Number(item.pagas)}/${Number(item.parcelas)} pagas</td><td>${item.pagas < item.parcelas ? `<button class="btn" data-pay="${item.id}">Pagar parcela</button> ` : '<span class="badge">Quitado</span> '}<button class="btn btn-danger" data-delete="parcelamentos:${item.id}">Excluir</button></td></tr>`).join('');
    return layout(`<div class="page-heading"><div><h1>Parcelamentos</h1><p>Compras parceladas e seu andamento.</p></div></div>${novo}<section class="card" style="margin-top:18px"><div class="card-head"><h2>Compras parceladas</h2></div>${db.parcelamentos.length ? `<div class="table-wrap"><table><thead><tr><th>Compra</th><th>Cartão</th><th>Valor total</th><th>Progresso</th><th></th></tr></thead><tbody>${linhas}</tbody></table></div>` : '<div class="empty">Nenhum parcelamento cadastrado ainda.</div>'}</section>`, 'Compras parceladas');
}

export const calendario = () => layout(calendarContent(db), 'Calendário');
