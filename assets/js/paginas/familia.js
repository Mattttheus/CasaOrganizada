// Família e acessos: contas de login (admin gerencia; cada um edita a própria) e membros sem login.
import { mode } from '../backend.js';
import { db, estado } from '../contexto.js';
import { esc, dateBR } from '../helpers.js';
import { campo, field, form, marcado } from '../ui.js';
import { layout } from './layout.js';

const avatar = nome => `<span class="avatar" style="display:inline-grid;margin-right:8px">${esc(String(nome || '?')[0])}</span>`;

/** Formulário de uma conta (nova ou existente). Admin edita tudo; cada um edita a própria. */
function contaForm(u) {
    const nova = !u, propria = u?.id === estado.session?.id, admin = db.user?.admin;
    const id = u?.id || 'nova';
    const permissoes = admin ? `<div class="field full checks">${marcado('▲ Acesso aos investimentos', 'invest', u?.invest)}${marcado('★ Administrador (gerencia os acessos)', 'admin', u?.admin)}</div>` : '';
    const excluir = !nova && admin && !propria ? `<button class="btn btn-danger" type="button" data-excluir-usuario="${esc(u.id)}" data-nome="${esc(u.nome)}">Excluir acesso</button>` : '';
    return `<form data-form="${nova ? 'usuario-novo' : 'usuario-editar'}" class="form-grid">
        ${nova ? '' : `<input type="hidden" name="id" value="${esc(u.id)}">`}
        ${campo(id, 'Nome', 'nome', 'text', `required maxlength="100" value="${esc(u?.nome || '')}"`)}
        ${campo(id, 'E-mail (login)', 'email', 'email', `required maxlength="150" value="${esc(u?.email || '')}"`)}
        ${propria && !nova ? campo(id, 'Senha atual', 'senha_atual', 'password', 'autocomplete="current-password" placeholder="só para trocar a senha"') : ''}
        ${campo(id, nova ? 'Senha' : 'Nova senha', 'senha', 'password', `${nova ? 'required' : ''} minlength="8" autocomplete="new-password" placeholder="${nova ? 'mín. 8, letras e números' : 'deixe em branco para manter'}"`)}
        ${permissoes}
        <div class="field full objetivo-acoes"><button class="btn btn-primary" type="submit">${nova ? 'Criar acesso' : 'Salvar'}</button>${excluir}</div>
    </form>`;
}

function acessos() {
    if (mode !== 'mysql') return '';
    const contas = db.usuarios;
    const linhas = contas.map(u => {
        const propria = u.id === estado.session?.id;
        const permissoes = `${u.admin ? '<span class="badge gold">★ Admin</span> ' : ''}${u.invest ? '<span class="badge">▲ Investimentos</span>' : '<span class="muted">Casa</span>'}`;
        return `<tr><td>${avatar(u.nome)}<strong>${esc(u.nome)}</strong>${propria ? ' <span class="badge">você</span>' : ''}</td><td>${esc(u.email)}</td><td>${permissoes}</td><td>${u.criado_em ? dateBR(String(u.criado_em).slice(0, 10)) : '-'}</td></tr>
            <tr class="linha-editar"><td colspan="4"><details class="objetivo-editar"><summary>Editar ${propria ? 'minha conta' : esc(u.nome)}</summary>${contaForm(u)}</details></td></tr>`;
    }).join('');
    const rodape = db.user?.admin
        ? `<div class="card-body"><details class="objetivo-editar"><summary>+ Novo acesso ao sistema</summary>${contaForm(null)}</details></div>`
        : '<div class="card-body"><small class="muted">Só administradores cadastram e excluem acessos. Você pode editar o seu nome, e-mail e senha.</small></div>';
    return `<section class="card" style="margin-bottom:18px"><div class="card-head"><h2>Acessos ao sistema</h2><span class="badge">${contas.length} ${contas.length === 1 ? 'conta' : 'contas'}</span></div>
        ${contas.length ? `<div class="table-wrap"><table><thead><tr><th>Nome</th><th>E-mail (login)</th><th>Permissões</th><th>Desde</th></tr></thead><tbody>${linhas}</tbody></table></div>` : '<div class="empty">Carregando acessos…</div>'}
        ${rodape}</section>`;
}

function listaMembros() {
    if (!db.membros.length) return '<div class="empty">Nenhum membro cadastrado ainda.</div>';
    const linhas = db.membros.map(item => `<tr><td>${avatar(item.nome)}${esc(item.nome)}</td><td>${esc(item.parentesco || '-')}</td><td><button class="btn btn-danger" data-delete="membros:${item.id}">Excluir</button></td></tr>
        <tr class="linha-editar"><td colspan="3"><details class="objetivo-editar"><summary>Editar ${esc(item.nome)}</summary><form data-form="editar-membro" class="form-grid"><input type="hidden" name="id" value="${esc(item.id)}">${campo(item.id, 'Nome', 'nome', 'text', `required maxlength="100" value="${esc(item.nome)}"`)}${campo(item.id, 'Parentesco', 'parentesco', 'text', `maxlength="50" value="${esc(item.parentesco || '')}"`)}<div class="field full"><button class="btn btn-primary" type="submit">Salvar</button></div></form></details></td></tr>`).join('');
    return `<div class="table-wrap"><table><thead><tr><th>Nome</th><th>Parentesco</th><th></th></tr></thead><tbody>${linhas}</tbody></table></div>`;
}

export function membros() {
    const novo = form(field('Nome', 'nome', 'text', 'required maxlength="100" placeholder="Ex: Maria"') + field('Parentesco', 'parentesco', 'text', 'maxlength="50" placeholder="Ex: Filho(a)"'), 'add-membro', 'Novo membro');
    return layout(`<div class="page-heading"><div><h1>Família e acessos</h1><p>Quem entra no sistema e quem faz parte da vida financeira da casa.</p></div></div>
        ${acessos()}
        <section class="card"><div class="card-head"><h2>Membros da família</h2><small class="muted">pessoas sem login (filhos, avós…)</small></div>${listaMembros()}</section>
        <div style="margin-top:18px">${novo}</div>`, 'Família');
}
