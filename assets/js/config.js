// Escolha do backend:
//   'mysql'    — API PHP local (api/index.php) + MySQL do WampServer.
//                Importe database/mysql.sql pelo phpMyAdmin.
//   'demo'     — vitrine: dados de exemplo, sem login e sem gravar nada.
//   'supabase' — Supabase (Auth + Postgres), para ter dados reais online
//                (GitHub Pages / Vercel não rodam PHP). Preencha abaixo.
//   'auto'     — 'demo' no GitHub Pages e na Vercel (só arquivos estáticos);
//                'mysql' em qualquer outro endereço (localhost, rede de casa,
//                Tailscale 100.x, IP público com a porta aberta no roteador).
export const BACKEND = 'auto';

// Configuração do Supabase (só usada no modo 'supabase').
//
// 1. Crie um projeto em https://app.supabase.com (gratuito).
// 2. Rode o arquivo database/supabase.sql no SQL Editor do projeto.
// 3. Copie a "Project URL" e a chave "anon public" em
//    Settings > API e cole abaixo.
//
// A chave "anon public" NÃO é secreta — ela é feita para ir no navegador.
// A segurança real vem do Row Level Security (RLS) configurado no SQL.
// NUNCA coloque aqui a chave "service_role".
export const SUPABASE_URL = 'https://SEU-PROJETO.supabase.co';
export const SUPABASE_ANON_KEY = 'SUA-CHAVE-ANON-PUBLICA';
