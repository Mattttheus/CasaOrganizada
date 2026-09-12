// Camada de dados: todas as leituras/escritas passam pelo Supabase
// (Postgres + RLS). Nada é salvo em localStorage além da sessão de login,
// que o próprio supabase-js já cuida de persistir.
import { supabase } from './supabaseClient.js';

const TABLES = {
    membros: 'membros_familia',
    cartoes: 'cartoes',
    receitas: 'receitas',
    despesas: 'despesas',
    parcelamentos: 'parcelamentos',
    notas: 'notas_tarefas',
};

function mustOk({ error }) {
    if (error) throw new Error(error.message);
}

export async function loadAll() {
    const [membros, cartoes, receitas, despesas, parcelamentos, notas] = await Promise.all([
        supabase.from(TABLES.membros).select('*').order('nome'),
        supabase.from(TABLES.cartoes).select('*').order('nome'),
        supabase.from(TABLES.receitas).select('*').order('data', { ascending: false }),
        supabase.from(TABLES.despesas).select('*').order('data', { ascending: false }),
        supabase.from(TABLES.parcelamentos).select('*').order('data', { ascending: false }),
        supabase.from(TABLES.notas).select('*').order('data'),
    ]);
    for (const result of [membros, cartoes, receitas, despesas, parcelamentos, notas]) mustOk(result);
    return {
        membros: membros.data || [],
        cartoes: cartoes.data || [],
        receitas: receitas.data || [],
        despesas: despesas.data || [],
        parcelamentos: parcelamentos.data || [],
        notas: notas.data || [],
    };
}

export async function insertRow(collection, payload) {
    const { data, error } = await supabase.from(TABLES[collection]).insert(payload).select().single();
    if (error) throw new Error(error.message);
    return data;
}

export async function updateRow(collection, id, patch) {
    const { data, error } = await supabase.from(TABLES[collection]).update(patch).eq('id', id).select().single();
    if (error) throw new Error(error.message);
    return data;
}

export async function deleteRow(collection, id) {
    const { error } = await supabase.from(TABLES[collection]).delete().eq('id', id);
    if (error) throw new Error(error.message);
}

export async function getProfile(userId) {
    const { data, error } = await supabase.from('perfis').select('*').eq('id', userId).maybeSingle();
    if (error) throw new Error(error.message);
    return data;
}
