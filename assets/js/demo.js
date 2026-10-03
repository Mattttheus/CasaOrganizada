// Backend "vitrine" (modo demonstração do GitHub Pages): entra direto com um
// usuário de exemplo e mostra dados fictícios, sem login e sem gravar nada.
// Mesma interface de localApi.js e data.js; backend.js escolhe qual usar.
// As datas são relativas ao mês atual para o calendário sempre ter conteúdo.
const now = new Date();
const iso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const day = d => iso(new Date(now.getFullYear(), now.getMonth(), d)); // dia d do mês atual
const ago = days => iso(new Date(now.getFullYear(), now.getMonth(), now.getDate() - days)); // N dias atrás

const SAMPLE = {
    membros: [
        { id: 'm1', nome: 'Ana', parentesco: 'Mãe' },
        { id: 'm2', nome: 'Carlos', parentesco: 'Pai' },
        { id: 'm3', nome: 'Júlia', parentesco: 'Filha' },
    ],
    cartoes: [
        { id: 'c1', nome: 'Cartão da casa', banco: 'Banco Exemplo', limite: 5000, vencimento: 10, responsavel: 'Ana' },
        { id: 'c2', nome: 'Cartão reserva', banco: 'Banco Modelo', limite: 2500, vencimento: 20, responsavel: 'Carlos' },
    ],
    receitas: [
        { id: 'r1', descricao: 'Salário Ana', categoria: 'Salário', valor: 4200, data: day(5), tipo: 'Fixa', status: 'Recebido', observacao: null },
        { id: 'r2', descricao: 'Salário Carlos', categoria: 'Salário', valor: 3800, data: day(5), tipo: 'Fixa', status: 'Recebido', observacao: null },
        { id: 'r3', descricao: 'Freelance de design', categoria: 'Outros', valor: 900, data: day(22), tipo: 'Variável', status: 'Previsto', observacao: null },
    ],
    despesas: [
        { id: 'd1', descricao: 'Aluguel', categoria: 'Moradia', valor: 1800, data: day(8), pagamento: 'Boleto', gasto_por: 'Ana', tipo: 'Fixa', status: 'Pago', observacao: null },
        { id: 'd2', descricao: 'Mercado do mês', categoria: 'Alimentação', valor: 1150.4, data: ago(3), pagamento: 'Cartão de Crédito', gasto_por: 'Ana', tipo: 'Variável', status: 'Pago', observacao: null },
        { id: 'd3', descricao: 'Conta de luz', categoria: 'Moradia', valor: 210.75, data: day(15), pagamento: 'PIX', gasto_por: 'Carlos', tipo: 'Fixa', status: 'Previsto', observacao: null },
        { id: 'd4', descricao: 'Internet', categoria: 'Moradia', valor: 119.9, data: day(18), pagamento: 'Boleto', gasto_por: 'Carlos', tipo: 'Fixa', status: 'Previsto', observacao: null },
        { id: 'd5', descricao: 'Combustível', categoria: 'Transporte', valor: 320, data: ago(6), pagamento: 'Cartão de Débito', gasto_por: 'Carlos', tipo: 'Variável', status: 'Pago', observacao: null },
        { id: 'd6', descricao: 'Cinema em família', categoria: 'Lazer', valor: 140, data: ago(1), pagamento: 'Cartão de Crédito', gasto_por: 'Júlia', tipo: 'Variável', status: 'Pago', observacao: null },
    ],
    parcelamentos: [
        { id: 'p1', descricao: 'Geladeira', valor_total: 3600, parcelas: 12, pagas: 4, data: day(1), cartao: 'Cartão da casa' },
        { id: 'p2', descricao: 'Notebook escolar', valor_total: 2800, parcelas: 10, pagas: 7, data: day(1), cartao: 'Cartão reserva' },
    ],
    notas: [
        { id: 'n1', titulo: 'Reunião da escola', descricao: 'Levar boletim da Júlia', data: day(12), tipo: 'Tarefa', concluida: false },
        { id: 'n2', titulo: 'Revisar orçamento', descricao: null, data: day(28), tipo: 'Nota', concluida: false },
        { id: 'n3', titulo: 'Renovar seguro do carro', descricao: null, data: ago(2), tipo: 'Tarefa', concluida: true },
    ],
    metas: [
        { id: 'mg1', nome: 'Gastos do mês', periodo: 'Mensal', categoria: null, nota: 'Todas as despesas da casa.', ativa: true },
        { id: 'mg2', nome: 'Mercado', periodo: 'Mensal', categoria: 'Alimentação', nota: 'Inclui feira e padaria.', ativa: true },
        { id: 'mg3', nome: 'Lazer da semana', periodo: 'Semanal', categoria: 'Lazer', nota: 'Passeios, cinema e restaurantes.', ativa: true },
        { id: 'mg4', nome: 'Transporte no ano', periodo: 'Anual', categoria: 'Transporte', nota: null, ativa: true },
    ],
    meta_versoes: [
        { id: 'v1', meta_id: 'mg1', valor_limite: 3500, vigente_desde: `${now.getFullYear() - 1}-01-01`, nota: 'Meta criada', alterado_por: 'Ana' },
        { id: 'v2', meta_id: 'mg1', valor_limite: 4000, vigente_desde: day(1), nota: 'Aluguel aumentou', alterado_por: 'Carlos' },
        { id: 'v3', meta_id: 'mg2', valor_limite: 1200, vigente_desde: `${now.getFullYear() - 1}-01-01`, nota: 'Meta criada', alterado_por: 'Ana' },
        { id: 'v4', meta_id: 'mg3', valor_limite: 300, vigente_desde: `${now.getFullYear() - 1}-01-01`, nota: 'Meta criada', alterado_por: 'Ana' },
        { id: 'v5', meta_id: 'mg4', valor_limite: 5000, vigente_desde: `${now.getFullYear()}-01-01`, nota: 'Meta criada', alterado_por: 'Carlos' },
    ],
    objetivos: [
        { id: 'o1', nome: 'Comprar casa', categoria: 'Casa', valor_meta: 80000, valor_atual: 12500, prazo: `${now.getFullYear() + 4}-12-31` },
        { id: 'o2', nome: 'Comprar carro', categoria: 'Carro', valor_meta: 35000, valor_atual: 9800, prazo: `${now.getFullYear() + 2}-06-30` },
        { id: 'o3', nome: 'Viagens', categoria: 'Viagem', valor_meta: 8000, valor_atual: 3150, prazo: `${now.getFullYear() + 1}-01-15` },
        { id: 'o4', nome: 'Passeios', categoria: 'Passeio', valor_meta: 1500, valor_atual: 980, prazo: null },
    ],
};

const readOnly = () => { throw new Error('Modo vitrine: alterações desativadas.'); };

export async function getUser() { return { id: 'demo', nome: 'Visitante', email: 'vitrine@exemplo.com' }; }
export async function signIn() { return getUser(); }
export async function signUp() { return getUser(); }
export async function signOut() { /* vitrine não tem sessão */ }
export async function onSignedOut() { /* vitrine não tem sessão */ }
// Mesma ordenação dos backends reais (data mais recente primeiro, notas por data, nomes A-Z).
export async function loadAll() {
    const data = structuredClone(SAMPLE);
    const byDateDesc = (a, b) => b.data.localeCompare(a.data);
    data.receitas.sort(byDateDesc);
    data.despesas.sort(byDateDesc);
    data.parcelamentos.sort(byDateDesc);
    data.notas.sort((a, b) => a.data.localeCompare(b.data));
    data.membros.sort((a, b) => a.nome.localeCompare(b.nome));
    data.cartoes.sort((a, b) => a.nome.localeCompare(b.nome));
    return data;
}
export const insertRow = readOnly;
export const updateRow = readOnly;
export const deleteRow = readOnly;
