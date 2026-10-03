// Carrega os dados do servidor para o estado e controla entrada/saída da sessão.
import { backend } from './backend.js';
import { db, estado } from './contexto.js';
import { enderecoDeVolta } from './dominio.js';
import { paginaDaUrl, render } from './roteador.js';
import { toast } from './ui.js';

export async function recarregar() {
    Object.assign(db, await backend.loadAll());
    if (backend.usuarios) db.usuarios = await backend.usuarios().catch(() => []);
}

/** Resumo do Projeto invest (renda e posição por ativo). Não bloqueia a página: redesenha ao chegar. */
export async function carregarInvest() {
    if (!db.user?.invest || !backend.investResumo) return;
    try { db.invest = await backend.investResumo(); }
    catch (err) { db.invest = { erro: err.message }; }
    if (estado.page === 'dashboard' || estado.page === 'objetivos') render();
}

export function definirUsuario(user) {
    estado.session = user;
    db.user = user ? { name: user.nome, email: user.email, invest: Boolean(user.invest), admin: Boolean(user.admin) } : null;
}

/** Depois do login: volta ao invest se veio de lá (?voltar=), senão carrega os dados e abre a página. */
export async function entrar(user) {
    definirUsuario(user);
    const voltar = enderecoDeVolta(new URLSearchParams(location.search).get('voltar'), location.hostname);
    if (voltar && db.user.invest) { location.replace(voltar); return; }
    if (voltar) { history.replaceState(null, '', location.pathname + location.hash); toast('Sua conta não tem acesso à área de investimentos.'); }
    await recarregar();
    carregarInvest();
    const pagina = paginaDaUrl();
    estado.page = pagina === 'login' ? 'dashboard' : pagina;
    render();
}

export function aoSair() {
    definirUsuario(null);
    db.invest = null;
    estado.page = 'login';
    render();
}
