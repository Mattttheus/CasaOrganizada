import { supabase, configured } from './supabaseClient.js';
import { loadAll, insertRow, updateRow, deleteRow, getProfile } from './data.js';
import { calendarContent, calNav, calGoToday, calSelectDay, despesaStatus, receitaStatus } from './calendar.js';
import { esc, money, dateBR, todayStr } from './helpers.js';

const categories = ['Moradia', 'Alimentação', 'Transporte', 'Lazer', 'Salário', 'Outros'];
const today = todayStr();

let db = { user: null, membros: [], cartoes: [], receitas: [], despesas: [], parcelamentos: [], notas: [] };
let session = null;
let authMode = 'login';
let page = 'loading';

const total = list => list.reduce((sum, item) => sum + Number(item.valor || item.valorTotal || item.valor_total || 0), 0);
const form = (fields, submit, title) => `<section class="card"><div class="card-head"><h2>${title}</h2></div><div class="card-body"><form data-form="${submit}" class="form-grid">${fields}<div class="field full"><button class="btn btn-primary" type="submit">Salvar</button></div></form></div></section>`;
const field = (label, name, type = 'text', extra = '') => `<div class="field"><label for="${name}">${label}</label><input id="${name}" name="${name}" type="${type}" ${extra}></div>`;
const select = (label, name, options) => `<div class="field"><label for="${name}">${label}</label><select id="${name}" name="${name}">${options.map(option => `<option>${esc(option)}</option>`).join('')}</select></div>`;

function configWarning() {
    return `<main class="login"><section class="card login-box">
        <div class="brand" style="padding:0 0 25px;color:var(--green-dark)"><span class="brand-mark">⌂</span> Casa Organizada</div>
        <h1>Configuração pendente</h1>
        <p>Edite <code>assets/js/config.js</code> com a URL e a chave anon do seu projeto Supabase, depois rode <code>database/supabase.sql</code> no SQL Editor do projeto.</p>
    </section></main>`;
}

function loadingScreen() {
    return `<main class="login"><section class="card login-box" style="text-align:center"><p>Carregando…</p></section></main>`;
}

function login() {
    const isSignup = authMode === 'signup';
    return `<main class="login"><section class="card login-box">
        <div class="brand" style="padding:0 0 25px;color:var(--green-dark)"><span class="brand-mark">⌂</span> Casa Organizada</div>
        <h1>${isSignup ? 'Crie sua conta' : 'Suas finanças, em ordem.'}</h1>
        <p>${isSignup ? 'Cadastre-se para cuidar da casa junto com a família.' : 'Entre para cuidar da casa com mais clareza.'}</p>
        <form data-form="${isSignup ? 'signup' : 'login'}">
            ${isSignup ? field('Nome', 'nome', 'text', 'required placeholder="Seu nome"') : ''}
            ${field('E-mail', 'email', 'email', 'required placeholder="familia@exemplo.com"')}
            ${field('Senha', 'senha', 'password', 'required minlength="6" placeholder="••••••••"')}
            <button class="btn btn-primary" style="width:100%;margin-top:8px" type="submit">${isSignup ? 'Criar conta' : 'Entrar'}</button>
        </form>
        <small style="display:block;color:var(--muted);margin-top:18px">${isSignup ? 'Já tem conta?' : 'Ainda não tem conta?'} <a href="#" data-action="toggle-auth">${isSignup ? 'Entrar' : 'Criar conta'}</a></small>
    </section></main>`;
}

function layout(content, title) {
    const links = [['dashboard', 'Visão geral', '⌂'], ['receitas', 'Receitas', '↗'], ['despesas', 'Despesas', '↘'], ['cartoes', 'Cartões', '▣'], ['parcelamentos', 'Parcelamentos', '◷'], ['calendario', 'Calendário', '🗓'], ['membros', 'Família', '♧']];
    return `<div class="shell"><aside class="sidebar"><a class="brand" href="#/dashboard"><span class="brand-mark">⌂</span> Casa Organizada</a><nav class="nav">${links.map(([id, label, icon]) => `<a class="nav-link ${page === id ? 'active' : ''}" href="#/${id}"><span>${icon}</span>${label}</a>`).join('')}</nav><div class="sidebar-foot">Controle simples para uma casa mais leve.</div></aside><main class="main"><header class="topbar"><button class="btn mobile-menu" data-action="menu">☰</button><small>${title}</small><div class="user-chip"><span class="avatar">${esc(db.user?.name?.[0] || '?')}</span><span>${esc(db.user?.name || '')}</span><button class="btn" data-action="logout">Sair</button></div></header><div class="content">${content}</div></main></div>`;
}

function dashboard() {
    const receitas = total(db.receitas), despesas = total(db.despesas), saldo = receitas - despesas;
    const recent = [...db.despesas].slice(-6).reverse();
    const max = Math.max(receitas, despesas, 1);
    return layout(`<div class="page-heading"><div><h1>Visão geral</h1><p>Um retrato claro do dinheiro da sua família.</p></div><div class="actions"><a class="btn btn-primary" href="#/receitas">+ Receita</a><a class="btn" href="#/despesas">+ Despesa</a></div></div><div class="grid-metrics"><div class="card metric"><div class="metric-label">Receitas</div><div class="metric-value">${money(receitas)}</div></div><div class="card metric"><div class="metric-label">Despesas</div><div class="metric-value">${money(despesas)}</div></div><div class="card metric"><div class="metric-label">Saldo</div><div class="metric-value">${money(saldo)}</div></div><div class="card metric"><div class="metric-label">Itens cadastrados</div><div class="metric-value">${db.receitas.length + db.despesas.length}</div></div></div><div class="two-col"><section class="card"><div class="card-head"><h2>Fluxo do período</h2><span class="badge">Atual</span></div><div class="card-body"><div class="chart-bars"><div class="bar" style="height:${Math.max(receitas / max * 100, 4)}%"><label>Receitas</label></div><div class="bar expense" style="height:${Math.max(despesas / max * 100, 4)}%"><label>Despesas</label></div></div><div class="legend"><span><i class="dot"></i>Entradas</span><span><i class="dot red"></i>Saídas</span></div></div></section><section class="card"><div class="card-head"><h2>Últimas despesas</h2><a href="#/despesas" class="badge">Ver todas</a></div>${recent.length ? `<div class="table-wrap"><table><tbody>${recent.map(item => `<tr><td>${dateBR(item.data)}<br><strong>${esc(item.descricao)}</strong></td><td>${money(item.valor)}</td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Nenhuma despesa registrada.</div>'}</section></div>`, 'Dashboard financeiro');
}

function receitas() {
    return layout(`<div class="page-heading"><div><h1>Receitas</h1><p>Gerencie todas as entradas financeiras.</p></div></div>${form(field('Descrição', 'descricao', 'text', 'required placeholder="Ex: Salário, Freelance"') + select('Categoria', 'categoria', categories.slice(4)) + field('Valor', 'valor', 'number', 'required min="0.01" step="0.01"') + field('Data', 'data', 'date', `value="${today}" required`) + select('Tipo', 'tipo', ['Variável', 'Fixa']) + select('Status', 'status', ['Recebido', 'Previsto']) + `<div class="field full"><label for="observacao">Observação</label><textarea id="observacao" name="observacao"></textarea></div>`, 'add-receita', 'Nova receita')}<section class="card" style="margin-top:18px"><div class="card-head"><h2>Receitas lançadas</h2><span class="badge">${db.receitas.length} itens</span></div>${db.receitas.length ? `<div class="table-wrap"><table><thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Status</th><th></th></tr></thead><tbody>${db.receitas.map(item => { const status = receitaStatus(item); return `<tr><td>${dateBR(item.data)}</td><td><strong>${esc(item.descricao)}</strong></td><td>${esc(item.categoria)}</td><td>${money(item.valor)}</td><td><span class="badge ${status.cls}">${status.label}</span></td><td>${item.status === 'Previsto' ? `<button class="btn" data-toggle="receitas:${item.id}:status:Recebido">Recebido</button> ` : ''}<button class="btn btn-danger" data-delete="receitas:${item.id}">Excluir</button></td></tr>`; }).join('')}</tbody></table></div>` : '<div class="empty">Nenhuma receita registrada.</div>'}</section>`, 'Entradas financeiras');
}

function despesas() {
    return layout(`<div class="page-heading"><div><h1>Despesas</h1><p>Acompanhe cada saída financeira da casa.</p></div></div>${form(field('Descrição', 'descricao', 'text', 'required placeholder="Ex: Mercado, Luz, Internet"') + field('Valor', 'valor', 'number', 'required min="0.01" step="0.01"') + field('Data', 'data', 'date', `value="${today}" required`) + select('Categoria', 'categoria', categories.slice(0, 4).concat('Outros')) + select('Forma de pagamento', 'pagamento', ['PIX', 'Dinheiro', 'Cartão de Débito', 'Cartão de Crédito', 'Boleto']) + select('Tipo', 'tipo', ['Variável', 'Fixa']) + select('Status', 'status', ['Pago', 'Previsto']) + `<div class="field full"><label for="observacao">Observação</label><textarea id="observacao" name="observacao"></textarea></div>`, 'add-despesa', 'Nova despesa')}<section class="card" style="margin-top:18px"><div class="card-head"><h2>Histórico</h2><span class="badge red">${db.despesas.length} itens</span></div>${db.despesas.length ? `<div class="table-wrap"><table><thead><tr><th>Data</th><th>Descrição</th><th>Pagamento</th><th>Status</th><th>Valor</th><th></th></tr></thead><tbody>${db.despesas.map(item => { const status = despesaStatus(item); return `<tr><td>${dateBR(item.data)}</td><td><strong>${esc(item.descricao)}</strong><br><small>${esc(item.categoria)}</small></td><td>${esc(item.pagamento)}</td><td><span class="badge ${status.cls}">${status.label}</span></td><td>${money(item.valor)}</td><td>${item.status === 'Previsto' ? `<button class="btn" data-toggle="despesas:${item.id}:status:Pago">Pago</button> ` : ''}<button class="btn btn-danger" data-delete="despesas:${item.id}">Excluir</button></td></tr>`; }).join('')}</tbody></table></div>` : '<div class="empty">Nenhuma despesa registrada.</div>'}</section>`, 'Saídas financeiras');
}

function cartoes() {
    return layout(`<div class="page-heading"><div><h1>Cartões</h1><p>Acompanhe limites e gastos da família.</p></div></div><div class="cards-grid">${db.cartoes.length ? db.cartoes.map(card => { const spent = db.despesas.filter(item => item.pagamento === 'Cartão de Crédito').reduce((sum, item) => sum + Number(item.valor), 0); const percent = Math.min(spent / Number(card.limite || 1) * 100, 100); return `<article class="card card-tile"><h3>${esc(card.nome)}</h3><p>${esc(card.banco || 'Banco não informado')}</p><strong>${money(spent)}</strong> de ${money(card.limite)}<div class="limit"><i style="width:${percent}%"></i></div><small>Vencimento dia ${esc(card.vencimento)} · ${esc(card.responsavel)}</small><div style="margin-top:18px"><button class="btn btn-danger" data-delete="cartoes:${card.id}">Excluir</button></div></article>`; }).join('') : '<div class="card empty">Nenhum cartão cadastrado ainda.</div>'}</div><div style="margin-top:18px">${form(field('Nome', 'nome', 'text', 'required placeholder="Ex: Cartão principal"') + field('Banco', 'banco') + field('Limite total', 'limite', 'number', 'min="0" step="0.01"') + field('Dia de vencimento', 'vencimento', 'number', 'min="1" max="31" value="10"') + field('Responsável', 'responsavel', 'text', 'placeholder="Quem usa este cartão"'), 'add-cartao', 'Novo cartão')}</div>`, 'Cartões da família');
}

function parcelamentos() {
    return layout(`<div class="page-heading"><div><h1>Parcelamentos</h1><p>Compras parceladas e seu andamento.</p></div></div>${form(field('Compra', 'descricao', 'text', 'required placeholder="Ex: Notebook"') + field('Valor total', 'valorTotal', 'number', 'required min="0.01" step="0.01"') + field('Número de parcelas', 'parcelas', 'number', 'required min="2" max="48" value="2"') + field('Data da compra', 'data', 'date', `value="${today}" required`) + select('Cartão', 'cartao', db.cartoes.length ? db.cartoes.map(item => item.nome) : ['Nenhum cartão']), 'add-parcelamento', 'Novo parcelamento')}<section class="card" style="margin-top:18px"><div class="card-head"><h2>Compras parceladas</h2></div>${db.parcelamentos.length ? `<div class="table-wrap"><table><thead><tr><th>Compra</th><th>Cartão</th><th>Valor total</th><th>Progresso</th><th></th></tr></thead><tbody>${db.parcelamentos.map(item => `<tr><td><strong>${esc(item.descricao)}</strong><br><small>${item.parcelas} parcelas</small></td><td>${esc(item.cartao)}</td><td>${money(item.valor_total)}</td><td>${item.pagas}/${item.parcelas} pagas</td><td><button class="btn" data-pay="${item.id}">Pagar parcela</button> <button class="btn btn-danger" data-delete="parcelamentos:${item.id}">Excluir</button></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Nenhum parcelamento cadastrado ainda.</div>'}</section>`, 'Compras parceladas');
}

function membros() {
    return layout(`<div class="page-heading"><div><h1>Membros da família</h1><p>Organize quem participa da vida financeira.</p></div></div>${form(field('Nome', 'nome', 'text', 'required placeholder="Ex: Maria"') + field('Parentesco', 'parentesco', 'text', 'placeholder="Ex: Filho(a)"'), 'add-membro', 'Novo membro')}<section class="card" style="margin-top:18px"><div class="card-head"><h2>Membros</h2></div>${db.membros.length ? `<div class="table-wrap"><table><thead><tr><th>Nome</th><th>Parentesco</th><th>Status</th><th></th></tr></thead><tbody>${db.membros.map(item => `<tr><td><span class="avatar" style="display:inline-grid;margin-right:8px">${esc(item.nome[0])}</span>${esc(item.nome)}</td><td>${esc(item.parentesco || '-')}</td><td><span class="badge">Ativo</span></td><td><button class="btn btn-danger" data-delete="membros:${item.id}">Excluir</button></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">Nenhum membro cadastrado ainda.</div>'}</section>`, 'Família');
}

function calendario() { return layout(calendarContent(db), 'Calendário'); }

function render() {
    const root = document.querySelector('#app');
    if (!configured) { root.innerHTML = configWarning(); return; }
    if (page === 'loading') { root.innerHTML = loadingScreen(); return; }
    root.innerHTML = page === 'login' ? login() : ({ dashboard, receitas, despesas, cartoes, parcelamentos, membros, calendario }[page] || dashboard)();
}

function read(formEl) { return Object.fromEntries(new FormData(formEl).entries()); }

async function reload() { Object.assign(db, await loadAll()); }

async function afterLogin() {
    const profile = await getProfile(session.user.id).catch(() => null);
    db.user = { name: profile?.nome || session.user.email.split('@')[0], email: session.user.email };
    await reload();
    const hashPage = location.hash.replace('#/', '');
    page = hashPage && hashPage !== 'login' ? hashPage : 'dashboard';
    render();
}

async function boot() {
    render();
    if (!configured) return;
    try {
        const { data } = await supabase.auth.getSession();
        session = data.session;
        if (session) await afterLogin();
        else { page = 'login'; render(); }
    } catch (err) {
        toast('Erro ao conectar ao Supabase: ' + err.message);
        page = 'login'; render();
    }
    supabase.auth.onAuthStateChange(event => {
        if (event === 'SIGNED_OUT') { session = null; db.user = null; page = 'login'; render(); }
    });
}

async function doLogin(data) {
    const { data: result, error } = await supabase.auth.signInWithPassword({ email: data.email, password: data.senha });
    if (error) { toast('Não foi possível entrar: ' + error.message); return; }
    session = result.session;
    await afterLogin();
}

async function doSignup(data) {
    const { data: result, error } = await supabase.auth.signUp({ email: data.email, password: data.senha, options: { data: { nome: data.nome } } });
    if (error) { toast('Não foi possível criar a conta: ' + error.message); return; }
    if (result.session) { session = result.session; await afterLogin(); }
    else { toast('Conta criada! Verifique seu e-mail para confirmar antes de entrar.'); authMode = 'login'; render(); }
}

document.addEventListener('submit', async event => {
    event.preventDefault();
    const formEl = event.target, action = formEl.dataset.form, data = read(formEl);
    if (action === 'login') return doLogin(data);
    if (action === 'signup') return doSignup(data);
    try {
        const by = session.user.id;
        if (action === 'add-receita') await insertRow('receitas', { descricao: data.descricao, categoria: data.categoria, valor: Number(data.valor), data: data.data, tipo: data.tipo, status: data.status, observacao: data.observacao || null, criado_por: by });
        if (action === 'add-despesa') await insertRow('despesas', { descricao: data.descricao, categoria: data.categoria, valor: Number(data.valor), data: data.data, pagamento: data.pagamento, tipo: data.tipo, status: data.status, observacao: data.observacao || null, criado_por: by });
        if (action === 'add-cartao') await insertRow('cartoes', { nome: data.nome, banco: data.banco || null, limite: Number(data.limite || 0), vencimento: Number(data.vencimento || 0) || null, responsavel: data.responsavel || 'Sem responsável', criado_por: by });
        if (action === 'add-membro') await insertRow('membros', { nome: data.nome, parentesco: data.parentesco || null, criado_por: by });
        if (action === 'add-parcelamento') await insertRow('parcelamentos', { descricao: data.descricao, valor_total: Number(data.valorTotal), parcelas: Number(data.parcelas), data: data.data, cartao: data.cartao, criado_por: by });
        if (action === 'add-nota') await insertRow('notas', { titulo: data.titulo, descricao: data.descricao || null, data: data.data, tipo: data.tipo, criado_por: by });
        await reload();
        toast('Registro salvo com sucesso.');
        render();
    } catch (err) { toast('Erro ao salvar: ' + err.message); }
});

document.addEventListener('click', async event => {
    const toggleAuth = event.target.closest('[data-action="toggle-auth"]');
    if (toggleAuth) { event.preventDefault(); authMode = authMode === 'signup' ? 'login' : 'signup'; render(); return; }

    const calNavBtn = event.target.closest('[data-cal-nav]');
    if (calNavBtn) { const delta = Number(calNavBtn.dataset.calNav); if (delta === 0) calGoToday(); else calNav(delta); render(); return; }

    const calDayBtn = event.target.closest('[data-cal-day]');
    if (calDayBtn) { calSelectDay(calDayBtn.dataset.calDay); render(); return; }

    const del = event.target.closest('[data-delete]');
    if (del) {
        const [collection, id] = del.dataset.delete.split(':');
        try { await deleteRow(collection, id); await reload(); toast('Registro removido.'); render(); }
        catch (err) { toast('Erro ao excluir: ' + err.message); }
        return;
    }

    const toggle = event.target.closest('[data-toggle]');
    if (toggle) {
        const [collection, id, field_, raw] = toggle.dataset.toggle.split(':');
        const value = raw === 'true' ? true : raw === 'false' ? false : raw;
        try { await updateRow(collection, id, { [field_]: value }); await reload(); toast('Atualizado.'); render(); }
        catch (err) { toast('Erro ao atualizar: ' + err.message); }
        return;
    }

    const pay = event.target.closest('[data-pay]');
    if (pay) {
        const item = db.parcelamentos.find(row => row.id === pay.dataset.pay);
        if (item && item.pagas < item.parcelas) {
            try { await updateRow('parcelamentos', item.id, { pagas: item.pagas + 1 }); await reload(); toast('Parcela marcada como paga.'); render(); }
            catch (err) { toast('Erro ao atualizar parcela: ' + err.message); }
        }
        return;
    }

    if (event.target.closest('[data-action="logout"]')) { await supabase.auth.signOut(); }
});

window.addEventListener('hashchange', () => {
    const next = location.hash.replace('#/', '') || 'dashboard';
    if (next !== 'login' && !session) { page = 'login'; return render(); }
    page = next;
    render();
});

function toast(message) { const el = document.createElement('div'); el.className = 'toast'; el.textContent = message; document.body.append(el); setTimeout(() => el.remove(), 2200); }

boot();
