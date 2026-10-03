// Backend Supabase: login (Supabase Auth) e leituras/escritas nas tabelas
// (Postgres + RLS). Nada é salvo em localStorage além da sessão de login,
// que o próprio supabase-js já cuida de persistir.
//
// Mesma interface de localApi.js (backend MySQL); backend.js escolhe qual usar.
// Os usuários são devolvidos sempre como { id, nome, email }.
import { getSupabase } from './supabaseClient.js';

const TABLES = {
    membros: 'membros_familia',
    cartoes: 'cartoes',
    receitas: 'receitas',
    despesas: 'despesas',
    parcelamentos: 'parcelamentos',
    notas: 'notas_tarefas',
    objetivos: 'objetivos',
};

function mustOk({ error }) {
    if (error) throw new Error(error.message);
}

async function toUser(authUser) {
    if (!authUser) return null;
    const supabase = await getSupabase();
    const { data: profile } = await supabase.from('perfis').select('*').eq('id', authUser.id).maybeSingle();
    return { id: authUser.id, nome: profile?.nome || authUser.email.split('@')[0], email: authUser.email };
}

export async function getUser() {
    const supabase = await getSupabase();
    const { data } = await supabase.auth.getSession();
    return toUser(data.session?.user);
}

export async function signIn(email, senha) {
    const supabase = await getSupabase();
    const { data, error } = await supabase.auth.signInWithPassword({ email, password: senha });
    if (error) throw new Error(error.message);
    return toUser(data.session.user);
}

// Devolve null quando o Supabase exige confirmação por e-mail antes do login.
export async function signUp(nome, email, senha) {
    const supabase = await getSupabase();
    const { data, error } = await supabase.auth.signUp({ email, password: senha, options: { data: { nome } } });
    if (error) throw new Error(error.message);
    return data.session ? toUser(data.session.user) : null;
}

export async function signOut() {
    const supabase = await getSupabase();
    await supabase.auth.signOut();
}

export async function onSignedOut(callback) {
    const supabase = await getSupabase();
    supabase.auth.onAuthStateChange(event => { if (event === 'SIGNED_OUT') callback(); });
}

export async function loadAll() {
    const supabase = await getSupabase();
    const [membros, cartoes, receitas, despesas, parcelamentos, notas, objetivos] = await Promise.all([
        supabase.from(TABLES.membros).select('*').order('nome'),
        supabase.from(TABLES.cartoes).select('*').order('nome'),
        supabase.from(TABLES.receitas).select('*').order('data', { ascending: false }),
        supabase.from(TABLES.despesas).select('*').order('data', { ascending: false }),
        supabase.from(TABLES.parcelamentos).select('*').order('data', { ascending: false }),
        supabase.from(TABLES.notas).select('*').order('data'),
        supabase.from(TABLES.objetivos).select('*').order('criado_em'),
    ]);
    for (const result of [membros, cartoes, receitas, despesas, parcelamentos, notas, objetivos]) mustOk(result);
    return {
        membros: membros.data || [],
        cartoes: cartoes.data || [],
        receitas: receitas.data || [],
        despesas: despesas.data || [],
        parcelamentos: parcelamentos.data || [],
        notas: notas.data || [],
        objetivos: objetivos.data || [],
    };
}

export async function insertRow(collection, payload) {
    const supabase = await getSupabase();
    const { data, error } = await supabase.from(TABLES[collection]).insert(payload).select().single();
    if (error) throw new Error(error.message);
    return data;
}

export async function updateRow(collection, id, patch) {
    const supabase = await getSupabase();
    const { data, error } = await supabase.from(TABLES[collection]).update(patch).eq('id', id).select().single();
    if (error) throw new Error(error.message);
    return data;
}

export async function deleteRow(collection, id) {
    const supabase = await getSupabase();
    const { error } = await supabase.from(TABLES[collection]).delete().eq('id', id);
    if (error) throw new Error(error.message);
}
