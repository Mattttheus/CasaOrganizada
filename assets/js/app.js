// Ponto de entrada da SPA: registra os eventos e abre a sessão (ou a tela de login).
//
// Organização dos módulos:
//   backend.js        escolhe o backend (localApi.js = MySQL | data.js = Supabase | demo.js = vitrine)
//   contexto.js       estado compartilhado (dados, sessão, página) e constantes
//   dominio.js        regras de negócio puras (testadas em tests/dominio.test.mjs)
//   ui.js             componentes de interface e avisos
//   paginas/*.js      uma página por arquivo (layout, acesso, finanças, objetivos, família, investimentos)
//   roteador.js       desenha a página da rota atual
//   sincronizacao.js  carrega os dados e controla entrada/saída da sessão
//   acoes.js          formulários e cliques
import { backend, configured } from './backend.js';
import { registrarEventos } from './acoes.js';
import { estado } from './contexto.js';
import { render } from './roteador.js';
import { aoSair, entrar } from './sincronizacao.js';
import { toast } from './ui.js';

async function iniciar() {
    registrarEventos();
    render();
    if (!configured) return;
    try {
        const user = await backend.getUser();
        if (user) await entrar(user);
        else { estado.page = 'login'; render(); }
    } catch (err) {
        toast('Erro ao conectar ao servidor: ' + err.message);
        estado.page = 'login';
        render();
    }
    backend.onSignedOut(aoSair);
}

iniciar();
