// Moldura das páginas logadas: menu lateral, barra do topo e faixa da vitrine.
import { mode } from '../backend.js';
import { db, demo, estado, INVEST_URL } from '../contexto.js';
import { esc } from '../helpers.js';
import { temaBtn } from '../ui.js';

const LINKS = [['dashboard', 'Visão geral', '⌂'], ['receitas', 'Receitas', '↗'], ['despesas', 'Despesas', '↘'], ['cartoes', 'Cartões', '▣'],
    ['parcelamentos', 'Parcelamentos', '◷'], ['calendario', 'Calendário', '🗓'], ['metas', 'Metas de gastos', '◔'], ['objetivos', 'Objetivos', '◎'], ['membros', 'Família', '♧']];

const FAIXA_DEMO = '<div class="demo-banner"><strong>Modo vitrine</strong> — dados fictícios de exemplo. Explore à vontade: cadastrar, editar e excluir estão desativados.</div>';

export function layout(content, title) {
    const investLink = db.user?.invest ? `<a class="nav-link" href="${INVEST_URL}"><span>▲</span>Investimentos ↗</a>` : '';
    // Botão fixo no topo (uso local): leva ao Projeto invest ou explica a falta de permissão.
    const investBtn = mode !== 'mysql' ? '' : db.user?.invest
        ? `<a class="btn btn-invest" href="${INVEST_URL}">▲ Investimentos</a>`
        : '<button class="btn btn-invest" data-action="invest-sem-acesso">▲ Investimentos</button>';
    const nav = LINKS.map(([id, label, icon]) => `<a class="nav-link ${estado.page === id ? 'active' : ''}" href="#/${id}"><span>${icon}</span>${label}</a>`).join('');
    const sair = demo ? '<span class="badge gold">Vitrine</span>' : '<button class="btn" data-action="logout">Sair</button>';
    return `<div class="shell ${estado.menuAberto ? 'menu-aberto' : ''}">
        <aside class="sidebar"><a class="brand" href="#/dashboard"><span class="brand-mark">⌂</span> Casa Organizada</a><nav class="nav">${nav}${investLink}</nav><div class="sidebar-foot">Controle simples para uma casa mais leve.</div></aside>
        <main class="main"><header class="topbar"><button class="btn mobile-menu" data-action="menu" aria-label="Abrir menu" aria-expanded="${estado.menuAberto}">☰</button><small>${title}</small>
            <div class="user-chip">${investBtn}${temaBtn()}<span class="avatar">${esc(db.user?.name?.[0] || '?')}</span><span>${esc(db.user?.name || '')}</span>${sair}</div></header>
            <div class="content">${demo ? FAIXA_DEMO : ''}${content}</div></main></div>`;
}
