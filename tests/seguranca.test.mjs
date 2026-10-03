// Auditoria de segurança no servidor real (WampServer), SÓ LEITURA: cabeçalhos, arquivos que não
// podem ser servidos, métodos perigosos e o login exigido pelo Casa e pelo Projeto invest.
// Host: variável CASA_HOST (padrão 192.168.1.51). Pula tudo se o servidor não responder.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { request } from 'node:http';

const HOST = process.env.CASA_HOST || '192.168.1.51';
const CASA = `http://${HOST}:8082`;
const INVEST = `http://${HOST}:8081`;
const WAMP = `http://${HOST}`;

const online = await fetch(`${CASA}/`, { signal: AbortSignal.timeout(3000) }).then(() => true, () => false);
const opcoes = { skip: online ? false : `servidor ${CASA} fora do ar` };

const pedir = (url, init = {}) => fetch(url, { redirect: 'manual', ...init });
const status = async (url, init) => (await pedir(url, init)).status;

test('Casa: cabeçalhos de segurança e versões escondidas', opcoes, async () => {
    const r = await pedir(`${CASA}/`);
    assert.equal(r.status, 200);
    assert.match(r.headers.get('content-security-policy'), /default-src 'self'/);
    assert.match(r.headers.get('content-security-policy'), /frame-ancestors 'none'/);
    assert.equal(r.headers.get('x-frame-options'), 'DENY');
    assert.equal(r.headers.get('x-content-type-options'), 'nosniff');
    assert.equal(r.headers.get('referrer-policy'), 'same-origin');
    assert.equal(r.headers.get('server'), 'Apache');               // sem versão do Apache/PHP
    assert.equal(r.headers.get('x-powered-by'), null);
});

test('Casa: arquivos internos, código-fonte do git e banco não são servidos', opcoes, async () => {
    const bloqueados = ['/.git/config', '/.git/HEAD', '/database/mysql.sql', '/database/supabase.sql', '/api/config.php',
        '/api/config.local.php', '/api/nucleo/banco.php', '/api/modulos/usuarios.php', '/tests/api.test.mjs',
        '/README.md', '/.gitignore', '/assets/'];
    for (const caminho of bloqueados) assert.equal(await status(`${CASA}${caminho}`), 403, caminho);
});

test('Casa e invest: método TRACE desligado', opcoes, async () => {
    // fetch não envia TRACE; vai pelo módulo http
    const trace = url => new Promise((ok, erro) => request(url, { method: 'TRACE' }, r => { r.resume(); ok(r.statusCode); }).on('error', erro).end());
    assert.equal(await trace(`${CASA}/`), 405);
    assert.equal(await trace(`${INVEST}/app.js`), 405);
});

test('Casa: API exige login e não aceita POST de outros sites', opcoes, async () => {
    for (const acao of ['dados', 'usuarios', 'invest_resumo']) assert.equal(await status(`${CASA}/api/index.php?acao=${acao}`), 401, acao);
    const r = await pedir(`${CASA}/api/index.php?acao=login`, { method: 'POST', headers: { 'Content-Type': 'application/json', Origin: 'http://site-malicioso.com' }, body: '{}' });
    assert.equal(r.status, 403);
    const cadastro = await pedir(`${CASA}/api/index.php?acao=cadastro`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{"nome":"x","email":"x@x.com","senha":"Senha1234"}' });
    assert.equal(cadastro.status, 403);
});

test('Invest: sem login, a página leva ao login do Casa e os dados dão 401', opcoes, async () => {
    const r = await pedir(`${INVEST}/`);
    assert.equal(r.status, 302);
    assert.match(r.headers.get('location'), new RegExp(`^http://${HOST.replace(/\./g, '\\.')}:8082/\\?voltar=`));
    for (const caminho of ['/dados.php', '/resumo.php']) assert.equal(await status(`${INVEST}${caminho}`), 401, caminho);
    assert.equal(await status(`${INVEST}/api.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{"acao":"atualizar"}' }), 401);
    assert.equal(await status(`${INVEST}/planilha.php`), 302);
});

test('Invest: carteira, planilha e arquivos internos nunca são servidos direto', opcoes, async () => {
    const bloqueados = ['/dados.json', '/index.html', '/servidor.php', '/portao.php', '/.htaccess', `/${encodeURIComponent('Gestao de investimentos.xlsm')}`];
    for (const caminho of bloqueados) assert.equal(await status(`${INVEST}${caminho}`), 403, caminho);
    const r = await pedir(`${INVEST}/app.js`);
    assert.equal(r.headers.get('x-frame-options'), 'DENY');
    assert.match(r.headers.get('content-security-policy'), /script-src 'self'/);
});

test('WAMP porta 80: dados do invest e pastas internas bloqueados', opcoes, async () => {
    const bloqueados = ['/Projeto%20invest/web/dados.json', '/Projeto%20invest/dados/lancamentos.csv', '/Projeto%20invest/.git/config', '/Projeto%20invest/gestao.py'];
    for (const caminho of bloqueados) assert.equal(await status(`${WAMP}${caminho}`), 403, caminho);
    assert.equal(await status(`${WAMP}/Projeto%20invest/web/`), 302);   // portão de login
});
