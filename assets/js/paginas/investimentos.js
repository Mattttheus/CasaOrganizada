// Card "Renda de investimentos" do painel: dividendos e proventos vindos do Projeto invest.
import { db, INVEST_URL } from '../contexto.js';
import { esc, money, dateBR } from '../helpers.js';

/** Ativos do Projeto invest com a posição atual (vazio se o invest não respondeu). */
export const ativosInvest = () => db.invest?.ativos_lista || [];

export function investCard() {
    if (!db.user?.invest) return '';
    const r = db.invest;
    const head = `<div class="card-head"><h2>Renda de investimentos</h2><a class="badge" href="${INVEST_URL}#proventos">Abrir painel ↗</a></div>`;
    if (!r) return `<section class="card invest-card">${head}<div class="card-body"><p class="muted">Carregando dividendos e proventos…</p></div></section>`;
    if (r.erro) return `<section class="card invest-card">${head}<div class="card-body"><p class="muted">Não foi possível ler os investimentos: ${esc(r.erro)}</p></div></section>`;
    const pct = Math.round((r.progresso || 0) * 100);
    const ultimos = r.ultimos_proventos?.length
        ? `<div class="table-wrap"><table><thead><tr><th>Data</th><th>Ativo</th><th>Tipo</th><th>Valor</th></tr></thead><tbody>${r.ultimos_proventos.map(p => `<tr><td>${dateBR(p.data)}</td><td><strong>${esc(p.ticker)}</strong></td><td>${esc(p.tipo || '-')}</td><td>${money(p.valor)}</td></tr>`).join('')}</tbody></table></div>`
        : `<div class="empty">Nenhum provento registrado ainda. <a href="${INVEST_URL}#lancar">Registrar no painel de investimentos ↗</a></div>`;
    const geradoEm = String(r.gerado_em || '').split(' ')[0].split('-').reverse().join('/');
    return `<section class="card invest-card">${head}<div class="card-body">
        <div class="invest-grid">
            <div><div class="metric-label">Renda mensal estimada</div><div class="invest-value">${money(r.renda_mensal)}</div></div>
            <div><div class="metric-label">Meta de renda mensal</div><div class="invest-value">${money(r.meta_mensal)}</div></div>
            <div><div class="metric-label">Recebido este mês</div><div class="invest-value">${money(r.recebido_mes)}</div></div>
            <div><div class="metric-label">Últimos 12 meses</div><div class="invest-value">${money(r.recebido_12m)}</div></div>
        </div>
        <div class="limit" title="${pct}% da meta"><i style="width:${pct}%"></i></div>
        <small class="muted">${pct}% da meta de renda · ${Number(r.ativos_com_renda)} de ${Number(r.ativos)} ativos pagando proventos · ${money(r.patrimonio_rv)} em renda variável · dados de ${esc(geradoEm)}</small>
    </div>${ultimos}</section>`;
}
