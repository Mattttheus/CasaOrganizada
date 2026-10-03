// Backend MySQL: conversa com api/index.php (PHP + MySQL do WampServer).
// Mesma interface de data.js (backend Supabase); backend.js escolhe qual usar.
const API = 'api/index.php';

async function call(acao, body) {
    const options = body === undefined
        ? { credentials: 'same-origin' }
        : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
    let response;
    try { response = await fetch(`${API}?acao=${acao}`, options); }
    catch { throw new Error('API local indisponível. O WampServer está rodando?'); }
    const data = await response.json().catch(() => {
        throw new Error('Resposta inválida da API local (o PHP está ativo no WampServer?).');
    });
    if (!response.ok) throw new Error(data.error || `Erro ${response.status}`);
    return data;
}

const signedOutListeners = [];

export async function getUser() {
    return (await call('sessao')).user;
}

export async function signIn(email, senha) {
    return (await call('login', { email, senha })).user;
}

export async function signUp(nome, email, senha) {
    return (await call('cadastro', { nome, email, senha })).user;
}

export async function signOut() {
    await call('sair', {});
    signedOutListeners.forEach(callback => callback());
}

export async function onSignedOut(callback) {
    signedOutListeners.push(callback);
}

// Renda de dividendos/proventos do Projeto invest (só contas com acesso_invest).
export async function investResumo() {
    return call('invest_resumo');
}

// Objetivos: lançamento (Dinheiro, ou Investimento = compra registrada no invest)
// e vínculo de ativos do invest a um objetivo.
export async function objetivoAporte(dados) {
    return call('objetivo_aporte', dados);
}

export async function objetivoVincular(objetivoId, ticker, vincular) {
    return call('objetivo_vincular', { objetivo_id: objetivoId, ticker, vincular });
}

// Contas de acesso ao sistema (página Família): admin gerencia todas; os demais, só a própria.
export async function usuarios() {
    return call('usuarios');
}

export async function usuarioSalvar(dados) {
    return call('usuario_salvar', dados);
}

export async function usuarioExcluir(id) {
    return call('usuario_excluir', { id });
}

// Metas de gastos (diárias/mensais/anuais) com histórico de limites.
export async function metaSalvar(dados) {
    return call('meta_salvar', dados);
}

export async function metaExcluir(id) {
    return call('meta_excluir', { id });
}

export async function loadAll() {
    return call('dados');
}

export async function insertRow(collection, payload) {
    return call('inserir', { colecao: collection, dados: payload });
}

export async function updateRow(collection, id, patch) {
    return call('atualizar', { colecao: collection, id, dados: patch });
}

export async function deleteRow(collection, id) {
    await call('excluir', { colecao: collection, id });
}
