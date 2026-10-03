// Estado compartilhado da aplicação (dados carregados, sessão, página atual) e constantes.
import { mode } from './backend.js';
import { todayStr } from './helpers.js';

export const demo = mode === 'demo';
export const today = todayStr();
export const DEMO_MSG = 'Modo vitrine: esta é só uma demonstração, nada é salvo.';
// Projeto invest: mesmo host, porta 8081. Só aparece para contas com acesso aos investimentos.
export const INVEST_URL = `${location.protocol}//${location.hostname}:8081/`;

// Dados carregados do backend (backend.loadAll + usuários + resumo do invest).
export const db = {
    user: null, invest: null, usuarios: [], membros: [], cartoes: [], receitas: [], despesas: [],
    parcelamentos: [], notas: [], objetivos: [], aportes: [], vinculos: [], metas: [], meta_versoes: [], nomes: {},
};

// session: usuário logado { id, nome, email, invest, admin }; page: rota atual (#/pagina).
// periodoGastos: período do relatório da página Despesas (Diária | Semanal | Mensal | Anual).
export const estado = { session: null, page: 'loading', authMode: 'login', menuAberto: false, periodoGastos: 'Mensal' };
