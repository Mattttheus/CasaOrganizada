// Escolhe e desenha a página da rota atual (#/pagina).
import { configured } from './backend.js';
import { estado } from './contexto.js';
import { configWarning, loadingScreen, login } from './paginas/acesso.js';
import { membros } from './paginas/familia.js';
import { calendario, cartoes, dashboard, despesas, parcelamentos, receitas } from './paginas/financas.js';
import { metas } from './paginas/metas.js';
import { objetivos } from './paginas/objetivos.js';

const PAGINAS = { dashboard, receitas, despesas, cartoes, parcelamentos, calendario, metas, objetivos, membros };

export const paginaDaUrl = () => location.hash.replace('#/', '') || 'dashboard';

export function render() {
    const root = document.querySelector('#app');
    if (!configured) { root.innerHTML = configWarning(); return; }
    if (estado.page === 'loading') { root.innerHTML = loadingScreen(); return; }
    if (estado.page === 'login') { root.innerHTML = login(); return; }
    root.innerHTML = (PAGINAS[estado.page] || dashboard)();
}
