// Página de Calendário: notas/tarefas e datas de contas a pagar (despesas)
// e a receber (receitas). Módulo decidido: não conhece `layout()` do app.js,
// só devolve o HTML de conteúdo — quem monta a página é app.js.
import { esc, money, dateBR, todayStr } from './helpers.js';

const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const MONTHS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

function startOfMonth(date) { const d = new Date(date); d.setDate(1); return d; }
function toISO(year, month, day) { return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`; }

export const calState = { month: startOfMonth(new Date()), selected: todayStr() };

export function calNav(delta) { calState.month.setMonth(calState.month.getMonth() + delta); }
export function calGoToday() { calState.month = startOfMonth(new Date()); calState.selected = todayStr(); }
export function calSelectDay(dateStr) { calState.selected = dateStr; }

export function despesaStatus(item) {
    if (item.status === 'Pago') return { label: 'Pago', cls: '' };
    if (item.data < todayStr()) return { label: 'Atrasado', cls: 'red' };
    return { label: 'Previsto', cls: 'gold' };
}
export function receitaStatus(item) {
    if (item.status !== 'Previsto') return { label: 'Recebido', cls: '' };
    if (item.data < todayStr()) return { label: 'Atrasado', cls: 'red' };
    return { label: 'Previsto', cls: 'gold' };
}

function dayItems(db, dateStr) {
    return {
        despesas: db.despesas.filter(item => item.data === dateStr),
        receitas: db.receitas.filter(item => item.data === dateStr),
        notas: db.notas.filter(item => item.data === dateStr),
    };
}

function monthGrid(db) {
    const year = calState.month.getFullYear();
    const month = calState.month.getMonth();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const firstWeekday = new Date(year, month, 1).getDay();
    const today = todayStr();
    const cells = [];
    for (let i = 0; i < firstWeekday; i++) cells.push('<div class="cal-day cal-day-empty"></div>');
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = toISO(year, month, day);
        const { despesas, receitas, notas } = dayItems(db, dateStr);
        const overdue = despesas.some(item => item.status === 'Previsto' && item.data < today) || receitas.some(item => item.status === 'Previsto' && item.data < today);
        const classes = ['cal-day'];
        if (dateStr === today) classes.push('is-today');
        if (dateStr === calState.selected) classes.push('is-selected');
        if (overdue) classes.push('has-overdue');
        cells.push(`<button type="button" class="${classes.join(' ')}" data-cal-day="${dateStr}">
            <span class="cal-day-num">${day}</span>
            <span class="cal-day-dots">
                ${despesas.length ? `<i class="dot red" title="${despesas.length} conta(s) a pagar"></i>` : ''}
                ${receitas.length ? `<i class="dot" title="${receitas.length} receita(s) a receber"></i>` : ''}
                ${notas.length ? `<i class="dot blue" title="${notas.length} nota(s)/tarefa(s)"></i>` : ''}
            </span>
        </button>`);
    }
    return `<div class="cal-grid">${WEEKDAYS.map(day => `<div class="cal-weekday">${day}</div>`).join('')}${cells.join('')}</div>`;
}

function monthSummary(db) {
    const year = calState.month.getFullYear();
    const month = calState.month.getMonth();
    const prefix = `${year}-${String(month + 1).padStart(2, '0')}`;
    const despesasMes = db.despesas.filter(item => item.data.startsWith(prefix));
    const receitasMes = db.receitas.filter(item => item.data.startsWith(prefix));
    const aPagar = despesasMes.filter(item => item.status === 'Previsto').reduce((sum, item) => sum + Number(item.valor), 0);
    const aReceber = receitasMes.filter(item => item.status === 'Previsto').reduce((sum, item) => sum + Number(item.valor), 0);
    const atrasadas = despesasMes.filter(item => item.status === 'Previsto' && item.data < todayStr()).length
        + receitasMes.filter(item => item.status === 'Previsto' && item.data < todayStr()).length;
    return `<div class="grid-metrics cal-summary">
        <div class="card metric"><div class="metric-label">A pagar no mês</div><div class="metric-value">${money(aPagar)}</div></div>
        <div class="card metric"><div class="metric-label">A receber no mês</div><div class="metric-value">${money(aReceber)}</div></div>
        <div class="card metric"><div class="metric-label">Itens atrasados</div><div class="metric-value">${atrasadas}</div></div>
    </div>`;
}

function dayDetail(db) {
    const { despesas, receitas, notas } = dayItems(db, calState.selected);
    const rowDespesa = item => { const status = despesaStatus(item); return `<tr><td><strong>${esc(item.descricao)}</strong></td><td>${money(item.valor)}</td><td><span class="badge ${status.cls}">${status.label}</span></td><td>${item.status === 'Previsto' ? `<button class="btn" data-toggle="despesas:${item.id}:status:Pago">Marcar pago</button>` : `<button class="btn" data-toggle="despesas:${item.id}:status:Previsto">Desfazer</button>`} <button class="btn btn-danger" data-delete="despesas:${item.id}">Excluir</button></td></tr>`; };
    const rowReceita = item => { const status = receitaStatus(item); return `<tr><td><strong>${esc(item.descricao)}</strong></td><td>${money(item.valor)}</td><td><span class="badge ${status.cls}">${status.label}</span></td><td>${item.status === 'Previsto' ? `<button class="btn" data-toggle="receitas:${item.id}:status:Recebido">Marcar recebido</button>` : `<button class="btn" data-toggle="receitas:${item.id}:status:Previsto">Desfazer</button>`} <button class="btn btn-danger" data-delete="receitas:${item.id}">Excluir</button></td></tr>`; };
    const rowNota = item => `<tr><td>${item.tipo === 'Tarefa' ? '☑' : '📝'} <strong class="${item.concluida ? 'cal-done' : ''}">${esc(item.titulo)}</strong>${item.descricao ? `<br><small>${esc(item.descricao)}</small>` : ''}</td><td><span class="badge">${item.tipo}</span></td><td>${!item.concluida ? `<button class="btn" data-toggle="notas:${item.id}:concluida:true">Concluir</button>` : `<button class="btn" data-toggle="notas:${item.id}:concluida:false">Reabrir</button>`} <button class="btn btn-danger" data-delete="notas:${item.id}">Excluir</button></td></tr>`;

    return `<section class="card cal-detail">
        <div class="card-head"><h2>${dateBR(calState.selected)}</h2><span class="badge">${despesas.length + receitas.length + notas.length} item(ns)</span></div>
        <div class="card-body stack">
            ${despesas.length ? `<div class="table-wrap"><table><thead><tr><th>Conta a pagar</th><th>Valor</th><th>Status</th><th></th></tr></thead><tbody>${despesas.map(rowDespesa).join('')}</tbody></table></div>` : ''}
            ${receitas.length ? `<div class="table-wrap"><table><thead><tr><th>Receita a receber</th><th>Valor</th><th>Status</th><th></th></tr></thead><tbody>${receitas.map(rowReceita).join('')}</tbody></table></div>` : ''}
            ${notas.length ? `<div class="table-wrap"><table><thead><tr><th>Nota/Tarefa</th><th>Tipo</th><th></th></tr></thead><tbody>${notas.map(rowNota).join('')}</tbody></table></div>` : ''}
            ${!despesas.length && !receitas.length && !notas.length ? '<div class="empty">Nada agendado para este dia.</div>' : ''}
            <form data-form="add-nota" class="form-grid">
                <input type="hidden" name="data" value="${calState.selected}">
                <div class="field"><label for="titulo">Nova nota ou tarefa</label><input id="titulo" name="titulo" required placeholder="Ex: Ligar para o banco"></div>
                <div class="field"><label for="tipo">Tipo</label><select id="tipo" name="tipo"><option>Tarefa</option><option>Nota</option></select></div>
                <div class="field full"><label for="descricao">Descrição (opcional)</label><textarea id="descricao" name="descricao"></textarea></div>
                <div class="field full"><button class="btn btn-primary" type="submit">Adicionar para ${dateBR(calState.selected)}</button></div>
            </form>
        </div>
    </section>`;
}

export function calendarContent(db) {
    const label = `${MONTHS[calState.month.getMonth()]} de ${calState.month.getFullYear()}`;
    return `<div class="page-heading">
            <div><h1>Calendário</h1><p>Notas, tarefas e datas de contas a pagar e a receber.</p></div>
            <div class="actions cal-nav">
                <button class="btn" data-cal-nav="-1">‹</button>
                <strong class="cal-month-label">${label}</strong>
                <button class="btn" data-cal-nav="1">›</button>
                <button class="btn" data-cal-nav="0">Hoje</button>
            </div>
        </div>
        ${monthSummary(db)}
        ${monthGrid(db)}
        <div style="margin-top:18px">${dayDetail(db)}</div>`;
}
