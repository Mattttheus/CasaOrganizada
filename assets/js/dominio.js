// Regras de negócio puras (sem DOM nem rede): cálculos de objetivos, totais e validação do
// endereço de volta do login. Testadas em tests/dominio.test.mjs.

/** Soma o valor de uma lista de lançamentos (receitas, despesas ou parcelamentos). */
export const somaValores = lista => lista.reduce((soma, item) => soma + Number(item.valor || item.valor_total || 0), 0);

/** As n despesas mais recentes (a lista já vem da mais nova para a mais antiga). */
export const maisRecentes = (lista, n = 6) => lista.slice(0, n);

/** Valor de mercado dos ativos do invest vinculados ao objetivo. */
export function investidoNoObjetivo(objetivo, vinculos = [], ativos = []) {
    return vinculos
        .filter(v => v.objetivo_id === objetivo.id)
        .reduce((soma, v) => soma + Number(ativos.find(a => a.ticker === v.ticker)?.posicao || 0), 0);
}

/**
 * Progresso de um objetivo: valor = dinheiro guardado + investido; percentual da meta e quanto
 * guardar por mês para chegar à meta no prazo (meses inteiros até o mês do prazo, mínimo 1).
 */
export function progressoObjetivo(objetivo, investido = 0, hoje = new Date()) {
    const meta = Number(objetivo.valor_meta || 0), dinheiro = Number(objetivo.valor_atual || 0);
    const atual = dinheiro + investido;
    const pct = meta > 0 ? Math.min(100, Math.round(atual / meta * 100)) : 0;
    let mensal = null, meses = null;
    if (meta > atual && objetivo.prazo) {
        const fim = new Date(objetivo.prazo + 'T00:00');
        meses = Math.max(1, (fim.getFullYear() - hoje.getFullYear()) * 12 + fim.getMonth() - hoje.getMonth());
        mensal = (meta - atual) / meses;
    }
    return { meta, atual, dinheiro, investido, pct, falta: Math.max(0, meta - atual), mensal, meses };
}

/** Totais da página de objetivos a partir dos progressos de cada um. */
export function resumoObjetivos(progressos) {
    const comMeta = progressos.filter(p => p.meta > 0);
    return {
        acumulado: progressos.reduce((s, p) => s + p.atual, 0),
        investido: progressos.reduce((s, p) => s + p.investido, 0),
        metas: progressos.reduce((s, p) => s + p.meta, 0),
        progressoMedio: comMeta.length ? Math.round(comMeta.reduce((s, p) => s + Math.min(1, p.atual / p.meta), 0) / comMeta.length * 100) : 0,
    };
}

/** Quanto cada pessoa lançou nos objetivos, do maior para o menor: [[nome, total], ...]. */
export function totalPorPessoa(aportes) {
    const totais = {};
    for (const a of aportes) {
        const nome = a.investido_por || 'Sem registro';
        totais[nome] = (totais[nome] || 0) + Number(a.valor);
    }
    return Object.entries(totais).sort((x, y) => y[1] - x[1]);
}

/**
 * Endereço para onde voltar depois do login (?voltar=, vindo do Projeto invest). Só aceita http(s)
 * no mesmo host; localhost deste PC vira o host atual (é o mesmo servidor). Senão, null.
 */
export function enderecoDeVolta(voltar, hostAtual) {
    try {
        const url = new URL(voltar || '');
        if (['localhost', '127.0.0.1', '[::1]'].includes(url.hostname)) url.hostname = hostAtual;
        return /^https?:$/.test(url.protocol) && url.hostname === hostAtual ? url.href : null;
    } catch { return null; }
}

// ------------------------------------------------------------ metas de gastos

const doisDigitos = n => String(n).padStart(2, '0');
/** Data local -> 'AAAA-MM-DD'. */
export const iso = d => `${d.getFullYear()}-${doisDigitos(d.getMonth() + 1)}-${doisDigitos(d.getDate())}`;
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/**
 * Período que contém `dia`, deslocado `atras` períodos para trás: {inicio, fim, rotulo, dias}.
 * Diária = o dia (relatório "hoje"); Semanal = domingo a sábado (como o calendário);
 * Mensal = 1º ao último dia do mês; Anual = 1º/jan a 31/dez.
 */
export function periodoDe(periodo, dia = new Date(), atras = 0) {
    const a = dia.getFullYear(), m = dia.getMonth(), d = dia.getDate();
    if (periodo === 'Diária') {
        const x = new Date(a, m, d - atras);
        return { inicio: iso(x), fim: iso(x), rotulo: `${doisDigitos(x.getDate())}/${doisDigitos(x.getMonth() + 1)}`, dias: 1 };
    }
    if (periodo === 'Semanal') {
        const ini = new Date(a, m, d - dia.getDay() - 7 * atras), fim = new Date(ini.getFullYear(), ini.getMonth(), ini.getDate() + 6);
        const dm = x => `${doisDigitos(x.getDate())}/${doisDigitos(x.getMonth() + 1)}`;
        return { inicio: iso(ini), fim: iso(fim), rotulo: `${dm(ini)} a ${dm(fim)}`, dias: 7 };
    }
    if (periodo === 'Mensal') {
        const ini = new Date(a, m - atras, 1), fim = new Date(a, m - atras + 1, 0);
        return { inicio: iso(ini), fim: iso(fim), rotulo: `${MESES[ini.getMonth()]}/${ini.getFullYear()}`, dias: fim.getDate() };
    }
    const ano = a - atras;
    return { inicio: `${ano}-01-01`, fim: `${ano}-12-31`, rotulo: String(ano), dias: (ano % 4 === 0 && ano % 100 !== 0) || ano % 400 === 0 ? 366 : 365 };
}

/** Despesas da meta (categoria ou todas) dentro do período: pagas = gasto real; previstas à parte. */
export function gastoNoPeriodo(despesas, categoria, inicio, fim) {
    let pago = 0, previsto = 0;
    for (const d of despesas) {
        if (d.data < inicio || d.data > fim || (categoria && d.categoria !== categoria)) continue;
        if (d.status === 'Previsto') previsto += Number(d.valor); else pago += Number(d.valor);
    }
    return { pago, previsto };
}

/** Limite que valia no período: a versão mais recente com vigente_desde até o fim dele (ou null). */
export function limiteVigente(versoes, fimDoPeriodo) {
    let atual = null;
    for (const v of versoes) if (v.vigente_desde <= fimDoPeriodo && (!atual || v.vigente_desde >= atual.vigente_desde)) atual = v;
    return atual ? Number(atual.valor_limite) : null;
}

/** Situação pelo percentual usado: ok (< 80%), atencao (80% a 100%), estourou (> 100%). */
export const situacao = pct => (pct > 100 ? 'estourou' : pct >= 80 ? 'atencao' : 'ok');

/**
 * Situação da meta no período atual (`atras` = 0) ou num anterior: gasto real × limite, e no
 * período atual a projeção até o fim e quanto ainda dá para gastar por dia.
 */
export function avaliarMeta(meta, versoes, despesas, hoje = new Date(), atras = 0) {
    const p = periodoDe(meta.periodo, hoje, atras);
    const limite = limiteVigente(versoes, p.fim);
    const { pago, previsto } = gastoNoPeriodo(despesas, meta.categoria, p.inicio, p.fim);
    const pct = limite ? Math.round(pago / limite * 100) : 0;
    const r = { ...p, limite, gasto: pago, previsto, pct, restante: limite === null ? null : limite - pago, situacao: situacao(pct), projecao: null, porDia: null };
    if (atras === 0 && meta.periodo !== 'Diária' && limite !== null) {
        const decorridos = Math.round((new Date(iso(hoje) + 'T00:00') - new Date(p.inicio + 'T00:00')) / 864e5) + 1;
        const restantes = p.dias - decorridos + 1;   // inclui hoje
        r.projecao = pago / decorridos * p.dias;
        r.porDia = Math.max(0, limite - pago) / restantes;
    }
    return r;
}

/** Histórico de períodos (mais recente primeiro): 8 semanas, 6 meses ou 3 anos. */
export function historicoMeta(meta, versoes, despesas, hoje = new Date()) {
    const quantos = { Semanal: 8, Mensal: 6, Anual: 3 }[meta.periodo] || 6;
    return Array.from({ length: quantos }, (_, atras) => avaliarMeta(meta, versoes, despesas, hoje, atras));
}

// ------------------------------------------------------------ relatório de gastos

/** Quem gastou: o informado na despesa ou, se vazio, quem lançou (nomes = {id_da_conta: nome}). */
export const quemGastou = (despesa, nomes = {}) => despesa.gasto_por || nomes[despesa.criado_por] || 'Sem registro';

const somarEm = (mapa, chave, valor) => mapa.set(chave, (mapa.get(chave) || 0) + valor);
const ordenarPorValor = mapa => [...mapa.entries()].sort((a, b) => b[1] - a[1]);

/**
 * Gastos reais (despesas pagas) de um período, para o gestor: total, previsto, por pessoa,
 * por categoria e linha do tempo (por dia; no período anual, por mês 'AAAA-MM').
 * Cada ponto da linha do tempo traz o total e quem gastou nele.
 */
export function resumoGastos(despesas, inicio, fim, nomes = {}, agruparPorMes = false) {
    const porPessoa = new Map(), porCategoria = new Map(), linha = new Map();
    let total = 0, previsto = 0, quantidade = 0;
    for (const d of despesas) {
        if (d.data < inicio || d.data > fim) continue;
        const valor = Number(d.valor);
        if (d.status === 'Previsto') { previsto += valor; continue; }
        const pessoa = quemGastou(d, nomes);
        total += valor;
        quantidade++;
        somarEm(porPessoa, pessoa, valor);
        somarEm(porCategoria, d.categoria || 'Outros', valor);
        const chave = agruparPorMes ? d.data.slice(0, 7) : d.data;
        const ponto = linha.get(chave) || { chave, total: 0, pessoas: new Map(), itens: 0 };
        ponto.total += valor;
        ponto.itens++;
        somarEm(ponto.pessoas, pessoa, valor);
        linha.set(chave, ponto);
    }
    return {
        total, previsto, quantidade,
        porPessoa: ordenarPorValor(porPessoa),
        porCategoria: ordenarPorValor(porCategoria),
        linhaDoTempo: [...linha.values()].sort((a, b) => b.chave.localeCompare(a.chave))
            .map(p => ({ chave: p.chave, total: p.total, itens: p.itens, pessoas: ordenarPorValor(p.pessoas) })),
    };
}
