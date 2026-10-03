// Testes de integração da API (api/index.php) num banco SEPARADO (casa_organizada_teste),
// recriado a cada execução a partir de database/mysql.sql, com um servidor PHP próprio
// (php -S na porta 8099). Não toca no banco real. Rodar: node --test tests/
//
// Caminhos do WampServer podem ser trocados por variáveis: CASA_PHP, CASA_MYSQL.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const RAIZ = join(dirname(fileURLToPath(import.meta.url)), '..');
const PHP = process.env.CASA_PHP || 'C:/wamp64.1/bin/php/php8.0.26/php.exe';
const MYSQL = process.env.CASA_MYSQL || 'C:/wamp64.1/bin/mysql/mysql8.0.31/bin/mysql.exe';
const BANCO = 'casa_organizada_teste';
const PORTA = 8099;
const URL_API = `http://127.0.0.1:${PORTA}/api/index.php`;

const SENHA = 'Senha123';
const CONTAS = {
    admin: { id: '11111111-1111-4111-8111-111111111111', nome: 'Admin Teste', email: 'admin@teste.local', invest: 1, admin: 1 },
    comum: { id: '22222222-2222-4222-8222-222222222222', nome: 'Comum Teste', email: 'comum@teste.local', invest: 0, admin: 0 },
};

let servidor;

const mysql = sqlTexto => execFileSync(MYSQL, ['-uroot', '--host=127.0.0.1', '--port=3306', '--default-character-set=utf8mb4'], { input: sqlTexto, encoding: 'utf8' });
const consulta = sqlTexto => mysql(`USE ${BANCO};\n${sqlTexto}`).trim().split('\n').slice(1);

before(async () => {
    const hash = execFileSync(PHP, ['-r', `echo password_hash('${SENHA}', PASSWORD_DEFAULT);`], { encoding: 'utf8' });
    const esquema = readFileSync(join(RAIZ, 'database/mysql.sql'), 'utf8').replace(/\bcasa_organizada\b/g, BANCO);
    mysql(`DROP DATABASE IF EXISTS ${BANCO};\n${esquema}`);
    const contas = Object.values(CONTAS).map(c => `('${c.id}', '${c.nome}', '${c.email}', '${hash}', ${c.invest}, ${c.admin})`).join(', ');
    mysql(`USE ${BANCO}; INSERT INTO usuarios (id, nome, email, senha, acesso_invest, admin) VALUES ${contas};`);

    servidor = spawn(PHP, ['-S', `127.0.0.1:${PORTA}`, '-t', RAIZ], {
        // invest_url aponta para uma porta sem nada: testa a falha controlada da ponte com o invest
        env: { ...process.env, CASA_DB_NAME: BANCO, CASA_DB_USER: 'root', CASA_DB_PASS: '', CASA_INVEST_URL: 'http://127.0.0.1:9' },
        stdio: 'ignore',
    });
    for (let i = 0; i < 50; i++) {
        try { await fetch(`${URL_API}?acao=sessao`); return; } catch { await new Promise(r => setTimeout(r, 100)); }
    }
    throw new Error('servidor PHP de teste não subiu');
});

after(() => {
    servidor?.kill();
    mysql(`DROP DATABASE IF EXISTS ${BANCO};`);
});

/** Cliente com cookie de sessão próprio (cada um é um navegador diferente). */
function cliente() {
    let cookie = '';
    async function pedir(acao, corpo, extras = {}) {
        const opcoes = { headers: { ...(cookie && { Cookie: cookie }), ...extras.headers }, method: corpo === undefined ? 'GET' : 'POST' };
        if (corpo !== undefined) {
            opcoes.headers['Content-Type'] ??= 'application/json';
            opcoes.body = typeof corpo === 'string' ? corpo : JSON.stringify(corpo);
        }
        if (extras.method) opcoes.method = extras.method;
        const r = await fetch(`${URL_API}?acao=${acao}`, opcoes);
        const novo = r.headers.get('set-cookie')?.match(/casaorganizada=[^;]+/)?.[0];
        if (novo) cookie = novo;
        return { status: r.status, json: await r.json().catch(() => null) };
    }
    return {
        pedir,
        entrar: (email, senha = SENHA) => pedir('login', { email, senha }),
        inserir: (colecao, dados) => pedir('inserir', { colecao, dados }),
        get cookie() { return cookie; },
    };
}

async function logado(conta) {
    const c = cliente();
    const r = await c.entrar(CONTAS[conta].email);
    assert.equal(r.status, 200, JSON.stringify(r.json));
    return c;
}

const limparTentativas = () => consulta('DELETE FROM tentativas_login;');

// ------------------------------------------------------------------ HTTP e roteamento

test('HTTP: ação inexistente 404, método errado 405, corpo sem JSON 415', async () => {
    const c = cliente();
    assert.equal((await c.pedir('nao_existe')).status, 404);
    assert.equal((await c.pedir('login', undefined)).status, 405);
    assert.equal((await c.pedir('dados', {})).status, 405);
    assert.equal((await c.pedir('login', 'email=x', { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })).status, 415);
});

test('HTTP: POST vindo de outro site (Origin diferente) é recusado', async () => {
    const r = await cliente().pedir('login', { email: CONTAS.admin.email, senha: SENHA }, { headers: { Origin: 'http://site-malicioso.com' } });
    assert.equal(r.status, 403);
});

test('HTTP: respostas da API não vão para cache e não podem ser embutidas', async () => {
    const r = await fetch(`${URL_API}?acao=sessao`);
    assert.match(r.headers.get('cache-control'), /no-store/);
    assert.match(r.headers.get('content-security-policy'), /default-src 'none'/);
    assert.equal(r.headers.get('x-content-type-options'), 'nosniff');
});

// ------------------------------------------------------------------ autenticação

test('Login: e-mail inexistente e senha errada dão a mesma mensagem (não revela contas)', async () => {
    await limparTentativas();
    const c = cliente();
    const inexistente = await c.entrar('ninguem@teste.local', 'Qualquer123');
    const errada = await c.entrar(CONTAS.comum.email, 'errada123');
    assert.equal(inexistente.status, 401);
    assert.equal(errada.status, 401);
    assert.equal(inexistente.json.error, errada.json.error);
    assert.equal((await c.pedir('sessao')).json.user, null);
});

test('Login: cadastro aberto está desligado (contas só pelo administrador)', async () => {
    const r = await cliente().pedir('cadastro', { nome: 'X', email: 'x@teste.local', senha: 'Senha123' });
    assert.equal(r.status, 403);
    assert.equal(consulta("SELECT COUNT(*) FROM usuarios WHERE email = 'x@teste.local';")[0], '0');
});

test('Login: sucesso abre sessão sem expor a senha; sair encerra', async () => {
    const c = await logado('comum');
    const sessao = await c.pedir('sessao');
    assert.equal(sessao.json.user.email, CONTAS.comum.email);
    assert.equal(sessao.json.user.senha, undefined);
    assert.equal((await c.pedir('sair', {})).status, 200);
    assert.equal((await c.pedir('sessao')).json.user, null);
    assert.equal((await c.pedir('dados')).status, 401);
});

test('Login: novo ID de sessão a cada login (proteção contra fixação de sessão)', async () => {
    const c = cliente();
    await c.pedir('sessao');
    const antes = c.cookie;
    await c.entrar(CONTAS.comum.email);
    assert.notEqual(c.cookie, antes);
});

test('Login: 5 senhas erradas bloqueiam o e-mail por 15 minutos (429), mesmo com a senha certa', async () => {
    await limparTentativas();
    const c = cliente();
    for (let i = 0; i < 5; i++) assert.equal((await c.entrar(CONTAS.comum.email, 'errada' + i)).status, 401);
    const bloqueado = await c.entrar(CONTAS.comum.email);
    assert.equal(bloqueado.status, 429);
    await limparTentativas();
    assert.equal((await c.entrar(CONTAS.comum.email)).status, 200);
});

test('Sessão: sem login, dados e ações devolvem 401', async () => {
    const c = cliente();
    assert.equal((await c.pedir('dados')).status, 401);
    assert.equal((await c.inserir('receitas', { descricao: 'x', valor: 1, data: '2026-10-01' })).status, 401);
    assert.equal((await c.pedir('usuarios')).status, 401);
});

// ------------------------------------------------------------------ coleções

test('Coleções: cria, atualiza e exclui uma receita; criado_por vem da sessão', async () => {
    const c = await logado('comum');
    const criada = await c.inserir('receitas', { descricao: 'Salário', valor: 4200, data: '2026-10-05', tipo: 'Fixa', status: 'Previsto', criado_por: CONTAS.admin.id });
    assert.equal(criada.status, 201);
    assert.equal(criada.json.valor, 4200);
    assert.equal(criada.json.criado_por, CONTAS.comum.id);            // ignorou o criado_por enviado
    const atualizada = await c.pedir('atualizar', { colecao: 'receitas', id: criada.json.id, dados: { status: 'Recebido' } });
    assert.equal(atualizada.json.status, 'Recebido');
    assert.equal((await c.pedir('excluir', { colecao: 'receitas', id: criada.json.id })).status, 200);
    assert.equal((await c.pedir('excluir', { colecao: 'receitas', id: criada.json.id })).status, 404);
});

test('Coleções: validações recusam dados inválidos com 422', async () => {
    const c = await logado('comum');
    const casos = [
        ['receitas', { descricao: '', valor: 10, data: '2026-10-01' }, /descrição/],
        ['receitas', { descricao: 'X', valor: -5, data: '2026-10-01' }, /mínimo/],
        ['receitas', { descricao: 'X', valor: 'abc', data: '2026-10-01' }, /número/],
        ['receitas', { descricao: 'X', valor: 10, data: '2026-02-30' }, /Data inválida/],
        ['despesas', { descricao: 'X', valor: 10, data: '2026-10-01', status: 'Talvez' }, /Use: Pago, Previsto/],
        ['cartoes', { nome: 'C', vencimento: 45 }, /no máximo 31/],
        ['parcelamentos', { descricao: 'P', valor_total: 100, parcelas: 3, pagas: 5, data: '2026-10-01' }, /Parcelas pagas/],
        ['parcelamentos', { descricao: 'P', valor_total: 100, parcelas: 2.5, data: '2026-10-01' }, /inteiro/],
        ['membros', { nome: 'x'.repeat(101) }, /no máximo 100/],
        ['notas', { titulo: 'a\u0000b', data: '2026-10-01' }, /caracteres inválidos/],
        ['objetivos', { nome: 'X', categoria: 'Iate' }, /Use: Casa/],
    ];
    for (const [colecao, dados, mensagem] of casos) {
        const r = await c.inserir(colecao, dados);
        assert.equal(r.status, 422, `${colecao} ${JSON.stringify(dados)} -> ${JSON.stringify(r.json)}`);
        assert.match(r.json.error, mensagem);
    }
});

test('Coleções: nome de coleção e ID são validados (sem injeção de SQL)', async () => {
    const c = await logado('comum');
    assert.equal((await c.inserir('usuarios', { nome: 'x' })).status, 400);
    assert.equal((await c.inserir('receitas; DROP TABLE receitas', {})).status, 400);
    assert.equal((await c.pedir('excluir', { colecao: 'receitas', id: "1' OR '1'='1" })).status, 422);
    assert.equal((await c.pedir('atualizar', { colecao: 'receitas', id: '1 OR 1=1', dados: { valor: 1 } })).status, 422);
    assert.equal(consulta("SHOW TABLES LIKE 'receitas';").length, 1);
});

test('Coleções: texto com HTML é guardado como texto (o front-end escapa ao mostrar)', async () => {
    const c = await logado('comum');
    const r = await c.inserir('membros', { nome: '<img src=x onerror=alert(1)>' });
    assert.equal(r.status, 201);
    assert.equal(r.json.nome, '<img src=x onerror=alert(1)>');
    await c.pedir('excluir', { colecao: 'membros', id: r.json.id });
});

// ------------------------------------------------------------------ objetivos

test('Objetivos: guardar dinheiro soma no objetivo e registra quem lançou', async () => {
    const c = await logado('comum');
    const o = await c.inserir('objetivos', { nome: 'Reserva de emergência – Teste', categoria: 'Reserva', valor_meta: 30000, valor_atual: 1000 });
    assert.equal(o.status, 201);
    const r = await c.pedir('objetivo_aporte', { objetivo_id: o.json.id, tipo: 'Dinheiro', valor: 250.5 });
    assert.equal(r.status, 201);
    const dados = (await c.pedir('dados')).json;
    assert.equal(dados.objetivos.find(x => x.id === o.json.id).valor_atual, 1250.5);
    const aporte = dados.aportes.find(a => a.objetivo_id === o.json.id);
    assert.equal(aporte.valor, 250.5);
    assert.equal(aporte.investido_por, CONTAS.comum.nome);
});

test('Objetivos: lançamento recusa valor zero, data futura e tipo inválido', async () => {
    const c = await logado('comum');
    const o = await c.inserir('objetivos', { nome: 'Viagem teste', categoria: 'Viagem' });
    const base = { objetivo_id: o.json.id, tipo: 'Dinheiro' };
    assert.equal((await c.pedir('objetivo_aporte', { ...base, valor: 0 })).status, 422);
    assert.equal((await c.pedir('objetivo_aporte', { ...base, valor: 10, data: '2099-01-01' })).status, 422);
    assert.equal((await c.pedir('objetivo_aporte', { ...base, tipo: 'Cripto', valor: 10 })).status, 422);
    assert.equal((await c.pedir('objetivo_aporte', { ...base, objetivo_id: 'nao-e-uuid', valor: 10 })).status, 422);
});

test('Objetivos: investir e vincular exigem acesso aos investimentos', async () => {
    const comum = await logado('comum');
    const o = await comum.inserir('objetivos', { nome: 'Carro teste', categoria: 'Carro' });
    assert.equal((await comum.pedir('objetivo_aporte', { objetivo_id: o.json.id, tipo: 'Investimento', ticker: 'TAEE11', quantidade: 1, preco: 10 })).status, 403);
    assert.equal((await comum.pedir('objetivo_vincular', { objetivo_id: o.json.id, ticker: 'TAEE11', vincular: true })).status, 403);
    assert.equal((await comum.pedir('invest_resumo')).status, 403);
});

test('Objetivos: um ativo só pode estar vinculado a um objetivo', async () => {
    const admin = await logado('admin');
    const a = await admin.inserir('objetivos', { nome: 'Obj A', categoria: 'Outro' });
    const b = await admin.inserir('objetivos', { nome: 'Obj B', categoria: 'Outro' });
    assert.equal((await admin.pedir('objetivo_vincular', { objetivo_id: a.json.id, ticker: 'TAEE11', vincular: true })).status, 200);
    const conflito = await admin.pedir('objetivo_vincular', { objetivo_id: b.json.id, ticker: 'TAEE11', vincular: true });
    assert.equal(conflito.status, 409);
    assert.equal((await admin.pedir('objetivo_vincular', { objetivo_id: a.json.id, ticker: 'TA;EE', vincular: true })).status, 422);
    assert.equal((await admin.pedir('objetivo_vincular', { objetivo_id: a.json.id, ticker: 'TAEE11', vincular: false })).status, 200);
    assert.equal((await admin.pedir('objetivo_vincular', { objetivo_id: b.json.id, ticker: 'TAEE11', vincular: true })).status, 200);
});

test('Objetivos: se o invest não responde, a API avisa sem quebrar (502)', async () => {
    const admin = await logado('admin');
    const r = await admin.pedir('invest_resumo');
    assert.equal(r.status, 502);
    assert.match(r.json.error, /não respondeu/);
});

test('Objetivos: excluir o objetivo apaga o histórico e os vínculos dele', async () => {
    const admin = await logado('admin');
    const o = await admin.inserir('objetivos', { nome: 'Temporário', categoria: 'Outro' });
    await admin.pedir('objetivo_aporte', { objetivo_id: o.json.id, tipo: 'Dinheiro', valor: 10 });
    await admin.pedir('objetivo_vincular', { objetivo_id: o.json.id, ticker: 'VALE3', vincular: true });
    assert.equal((await admin.pedir('excluir', { colecao: 'objetivos', id: o.json.id })).status, 200);
    assert.equal(consulta(`SELECT COUNT(*) FROM objetivo_aportes WHERE objetivo_id = '${o.json.id}';`)[0], '0');
    assert.equal(consulta(`SELECT COUNT(*) FROM objetivo_ativos WHERE objetivo_id = '${o.json.id}';`)[0], '0');
});

// ------------------------------------------------------------------ contas de acesso

test('Contas: admin vê todas; conta comum vê só a própria', async () => {
    const admin = await logado('admin');
    const comum = await logado('comum');
    assert.ok((await admin.pedir('usuarios')).json.length >= 2);
    const proprias = (await comum.pedir('usuarios')).json;
    assert.deepEqual(proprias.map(u => u.email), [CONTAS.comum.email]);
    assert.ok(proprias.every(u => u.senha === undefined));
});

test('Contas: admin cria acesso com senha forte e e-mail único', async () => {
    const admin = await logado('admin');
    const nova = { nome: 'Filho Teste', email: 'filho@teste.local', senha: 'Filho2026x', invest: false };
    assert.equal((await admin.pedir('usuario_salvar', { ...nova, senha: 'curta' })).status, 422);
    assert.equal((await admin.pedir('usuario_salvar', { ...nova, senha: 'semnumeros' })).status, 422);
    assert.equal((await admin.pedir('usuario_salvar', { ...nova, email: 'invalido' })).status, 422);
    assert.equal((await admin.pedir('usuario_salvar', nova)).status, 201);
    assert.equal((await admin.pedir('usuario_salvar', nova)).status, 409);
    assert.equal((await cliente().entrar(nova.email, nova.senha)).status, 200);
});

test('Contas: conta comum não cria, não edita outros e não se promove', async () => {
    const comum = await logado('comum');
    assert.equal((await comum.pedir('usuario_salvar', { nome: 'X', email: 'x2@teste.local', senha: 'Senha1234' })).status, 403);
    assert.equal((await comum.pedir('usuario_salvar', { id: CONTAS.admin.id, nome: 'Hack', email: CONTAS.admin.email })).status, 403);
    const promover = await comum.pedir('usuario_salvar', { id: CONTAS.comum.id, nome: CONTAS.comum.nome, email: CONTAS.comum.email, admin: true, invest: true });
    assert.equal(promover.status, 200);
    assert.deepEqual(consulta(`SELECT admin, acesso_invest FROM usuarios WHERE id = '${CONTAS.comum.id}';`), ['0\t0']);
    assert.equal((await comum.pedir('usuario_excluir', { id: CONTAS.admin.id })).status, 403);
});

test('Contas: trocar a própria senha exige a senha atual, com limite de tentativas', async () => {
    await limparTentativas();
    const comum = await logado('comum');
    const base = { id: CONTAS.comum.id, nome: CONTAS.comum.nome, email: CONTAS.comum.email, senha: 'NovaSenha99' };
    for (let i = 0; i < 5; i++) assert.equal((await comum.pedir('usuario_salvar', { ...base, senha_atual: 'errada' })).status, 422);
    assert.equal((await comum.pedir('usuario_salvar', { ...base, senha_atual: SENHA })).status, 429);
    await limparTentativas();
    assert.equal((await comum.pedir('usuario_salvar', { ...base, senha_atual: SENHA })).status, 200);
    assert.equal((await cliente().entrar(CONTAS.comum.email, 'NovaSenha99')).status, 200);
    await comum.pedir('usuario_salvar', { ...base, senha: SENHA, senha_atual: 'NovaSenha99' });   // devolve a senha original
});

test('Contas: ninguém exclui a própria conta e o sistema nunca fica sem administrador', async () => {
    const admin = await logado('admin');
    assert.equal((await admin.pedir('usuario_excluir', { id: CONTAS.admin.id })).status, 422);
    const tirarAdmin = await admin.pedir('usuario_salvar', { id: CONTAS.admin.id, nome: CONTAS.admin.nome, email: CONTAS.admin.email, admin: false, invest: true });
    assert.equal(tirarAdmin.status, 422);
    assert.match(tirarAdmin.json.error, /pelo menos um administrador/);
});

test('Contas: excluir um acesso derruba a sessão aberta da pessoa na hora', async () => {
    const admin = await logado('admin');
    await admin.pedir('usuario_salvar', { nome: 'Temporário', email: 'temp@teste.local', senha: 'Temp12345' });
    const temp = cliente();
    assert.equal((await temp.entrar('temp@teste.local', 'Temp12345')).status, 200);
    const id = consulta("SELECT id FROM usuarios WHERE email = 'temp@teste.local';")[0];
    assert.equal((await admin.pedir('usuario_excluir', { id })).status, 200);
    assert.equal((await temp.pedir('sessao')).json.user, null);
    assert.equal((await temp.pedir('dados')).status, 401);
});

test('Contas: retirar o acesso aos investimentos vale na hora, sem novo login', async () => {
    const admin = await logado('admin');
    await admin.pedir('usuario_salvar', { nome: 'Investidor', email: 'inv@teste.local', senha: 'Inv123456', invest: true });
    const inv = cliente();
    await inv.entrar('inv@teste.local', 'Inv123456');
    assert.equal((await inv.pedir('sessao')).json.user.invest, true);
    const id = consulta("SELECT id FROM usuarios WHERE email = 'inv@teste.local';")[0];
    await admin.pedir('usuario_salvar', { id, nome: 'Investidor', email: 'inv@teste.local', invest: false });
    assert.equal((await inv.pedir('sessao')).json.user.invest, false);
    assert.equal((await inv.pedir('invest_resumo')).status, 403);
});

// ------------------------------------------------------------------ metas de gastos

test('Metas: criar grava a meta e a 1ª versão do limite (com autor)', async () => {
    const c = await logado('comum');
    const r = await c.pedir('meta_salvar', { nome: 'Mercado', periodo: 'Mensal', categoria: 'Alimentação', valor_limite: 1200, vigente_desde: '2026-01-01', nota: 'Feira e padaria' });
    assert.equal(r.status, 201);
    const dados = (await c.pedir('dados')).json;
    const meta = dados.metas.find(m => m.id === r.json.id);
    assert.deepEqual([meta.nome, meta.periodo, meta.categoria, meta.nota, meta.ativa], ['Mercado', 'Mensal', 'Alimentação', 'Feira e padaria', true]);
    const versoes = dados.meta_versoes.filter(v => v.meta_id === meta.id);
    assert.deepEqual(versoes.map(v => [v.valor_limite, v.vigente_desde, v.alterado_por]), [[1200, '2026-01-01', CONTAS.comum.nome]]);
});

test('Metas: mudar o limite cria nova versão; editar só a nota não', async () => {
    const c = await logado('comum');
    const { json: { id } } = await c.pedir('meta_salvar', { nome: 'Casa', periodo: 'Mensal', categoria: 'Todas', valor_limite: 3500, vigente_desde: '2026-01-01' });
    const base = { id, nome: 'Casa', periodo: 'Mensal', categoria: 'Todas' };
    await c.pedir('meta_salvar', { ...base, valor_limite: 3500, nota: 'só a nota mudou' });
    const nova = await c.pedir('meta_salvar', { ...base, valor_limite: 4000, vigente_desde: '2026-10-01', nota_alteracao: 'Aluguel aumentou' });
    assert.match(nova.json.resumo, /histórico/);
    const dados = (await c.pedir('dados')).json;
    const versoes = dados.meta_versoes.filter(v => v.meta_id === id);
    assert.deepEqual(versoes.map(v => [v.valor_limite, v.vigente_desde, v.nota]), [[3500, '2026-01-01', 'Meta criada'], [4000, '2026-10-01', 'Aluguel aumentou']]);
    assert.equal(dados.metas.find(m => m.id === id).categoria, null);   // "Todas" = sem filtro
    assert.equal(dados.metas.find(m => m.id === id).nota, 'só a nota mudou');
});

test('Metas: pausar, validar e excluir (apaga o histórico de limites junto)', async () => {
    const c = await logado('comum');
    const invalidas = [
        [{ nome: '', periodo: 'Mensal', valor_limite: 10 }, /nome/],
        [{ nome: 'X', periodo: 'Semanal', valor_limite: 10 }, /Diária, Mensal, Anual/],
        [{ nome: 'X', periodo: 'Mensal', categoria: 'Pets', valor_limite: 10 }, /categoria/],
        [{ nome: 'X', periodo: 'Mensal', valor_limite: 0 }, /maior que zero/],
        [{ nome: 'X', periodo: 'Mensal', valor_limite: 10, vigente_desde: '2026-13-01' }, /Data inválida/],
        [{ nome: 'X', periodo: 'Mensal', valor_limite: 10, nota: 'x'.repeat(1001) }, /no máximo 1000/],
    ];
    for (const [corpo, msg] of invalidas) {
        const r = await c.pedir('meta_salvar', corpo);
        assert.equal(r.status, 422, JSON.stringify(corpo));
        assert.match(r.json.error, msg);
    }
    const { json: { id } } = await c.pedir('meta_salvar', { nome: 'Lazer', periodo: 'Diária', categoria: 'Lazer', valor_limite: 50 });
    await c.pedir('meta_salvar', { id, nome: 'Lazer', periodo: 'Diária', categoria: 'Lazer', valor_limite: 50, ativa: false });
    assert.equal((await c.pedir('dados')).json.metas.find(m => m.id === id).ativa, false);
    assert.equal((await c.pedir('meta_excluir', { id })).status, 200);
    assert.equal(consulta(`SELECT COUNT(*) FROM meta_gasto_versoes WHERE meta_id = '${id}';`)[0], '0');
    assert.equal((await c.pedir('meta_excluir', { id })).status, 404);
    assert.equal((await cliente().pedir('meta_salvar', { nome: 'X', periodo: 'Mensal', valor_limite: 10 })).status, 401);
});

// ------------------------------------------------------------------ despesas: quem gastou

test('Despesas: guardam quem gastou e os dados trazem o nome de quem lançou', async () => {
    const c = await logado('comum');
    const r = await c.inserir('despesas', { descricao: 'Mercado', valor: 230.4, data: '2026-10-03', categoria: 'Alimentação', status: 'Pago', gasto_por: 'Júlia' });
    assert.equal(r.status, 201);
    assert.equal(r.json.gasto_por, 'Júlia');
    assert.equal((await c.inserir('despesas', { descricao: 'X', valor: 1, data: '2026-10-03', gasto_por: 'x'.repeat(101) })).status, 422);
    const dados = (await c.pedir('dados')).json;
    assert.equal(dados.nomes[CONTAS.comum.id], CONTAS.comum.nome);
    assert.equal(dados.despesas.find(d => d.id === r.json.id).criado_por, CONTAS.comum.id);
});
