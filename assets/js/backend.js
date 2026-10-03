// Escolhe o backend (MySQL local, Supabase ou vitrine) conforme BACKEND em config.js.
import { BACKEND } from './config.js';
import { configured as supabaseConfigured } from './supabaseClient.js';
import * as supabaseBackend from './data.js';
import * as localBackend from './localApi.js';
import * as demoBackend from './demo.js';

// Hospedagens só de arquivos estáticos (sem a API PHP): lá o app abre como vitrine.
// Em qualquer outro endereço — localhost, IP da rede, Tailscale (100.x / *.ts.net),
// IP público ou domínio apontando para o servidor de casa — usa a API local (MySQL).
function hospedagemEstatica() {
    const host = location.hostname;
    return location.protocol === 'file:' || /(^|\.)github\.io$/.test(host) || /(^|\.)vercel\.app$/.test(host);
}

export const mode = BACKEND === 'auto' ? (hospedagemEstatica() ? 'demo' : 'mysql') : BACKEND;
export const backend = { mysql: localBackend, supabase: supabaseBackend, demo: demoBackend }[mode];
export const configured = mode !== 'supabase' || supabaseConfigured;
