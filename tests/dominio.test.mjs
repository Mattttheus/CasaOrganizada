// Regras de negócio puras do front-end (assets/js/dominio.js). Rodar: node --test tests/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    enderecoDeVolta, investidoNoObjetivo, maisRecentes, progressoObjetivo, resumoObjetivos, somaValores, totalPorPessoa,
} from '../assets/js/dominio.js';

const hoje = new Date(2026, 9, 3); // 03/10/2026

test('somaValores soma valor e valor_total, ignorando vazios', () => {
    assert.equal(somaValores([{ valor: 10.5 }, { valor: '4.5' }, { valor_total: 100 }, {}]), 115);
    assert.equal(somaValores([]), 0);
});

test('maisRecentes pega o início da lista (já vem da mais nova para a mais antiga)', () => {
    const lista = [1, 2, 3, 4, 5, 6, 7, 8].map(n => ({ n }));
    assert.deepEqual(maisRecentes(lista).map(x => x.n), [1, 2, 3, 4, 5, 6]);
    assert.deepEqual(maisRecentes(lista, 2).map(x => x.n), [1, 2]);
});

test('investidoNoObjetivo soma só a posição dos ativos vinculados a ele', () => {
    const vinculos = [{ ticker: 'TAEE11', objetivo_id: 'a' }, { ticker: 'VALE3', objetivo_id: 'b' }, { ticker: 'XXXX3', objetivo_id: 'a' }];
    const ativos = [{ ticker: 'TAEE11', posicao: 836.8 }, { ticker: 'VALE3', posicao: 500 }];
    assert.equal(investidoNoObjetivo({ id: 'a' }, vinculos, ativos), 836.8);   // XXXX3 sem posição conta 0
    assert.equal(investidoNoObjetivo({ id: 'b' }, vinculos, ativos), 500);
    assert.equal(investidoNoObjetivo({ id: 'c' }, vinculos, ativos), 0);
});

test('progressoObjetivo: dinheiro + investido, percentual e quanto guardar por mês', () => {
    const p = progressoObjetivo({ valor_meta: 5000, valor_atual: 350, prazo: '2027-12-31' }, 0, hoje);
    assert.equal(p.atual, 350);
    assert.equal(p.pct, 7);
    assert.equal(p.falta, 4650);
    assert.equal(p.meses, 14);                       // out/2026 -> dez/2027
    assert.equal(p.mensal.toFixed(2), '332.14');
});

test('progressoObjetivo soma o investido e limita o percentual a 100', () => {
    const p = progressoObjetivo({ valor_meta: 1000, valor_atual: 400 }, 800, hoje);
    assert.equal(p.atual, 1200);
    assert.equal(p.pct, 100);
    assert.equal(p.falta, 0);
    assert.equal(p.mensal, null);
});

test('progressoObjetivo sem meta ou sem prazo não calcula parcela mensal', () => {
    assert.deepEqual(
        [progressoObjetivo({ valor_atual: 250 }, 0, hoje).pct, progressoObjetivo({ valor_atual: 250 }, 0, hoje).mensal],
        [0, null],
    );
    assert.equal(progressoObjetivo({ valor_meta: 30000, valor_atual: 2100 }, 0, hoje).mensal, null);
});

test('progressoObjetivo com prazo no mês atual ou vencido usa no mínimo 1 mês', () => {
    assert.equal(progressoObjetivo({ valor_meta: 100, valor_atual: 0, prazo: '2026-10-20' }, 0, hoje).meses, 1);
    assert.equal(progressoObjetivo({ valor_meta: 100, valor_atual: 0, prazo: '2025-01-01' }, 0, hoje).meses, 1);
});

test('resumoObjetivos: totais e progresso médio só dos que têm meta', () => {
    const r = resumoObjetivos([
        { atual: 25000, investido: 0, meta: 50000 },
        { atual: 2100, investido: 0, meta: 30000 },
        { atual: 836.8, investido: 836.8, meta: 0 },
    ]);
    assert.equal(r.acumulado, 27936.8);
    assert.equal(r.investido, 836.8);
    assert.equal(r.metas, 80000);
    assert.equal(r.progressoMedio, 29);              // (50% + 7%) / 2
    assert.equal(resumoObjetivos([]).progressoMedio, 0);
});

test('totalPorPessoa agrupa e ordena do maior para o menor', () => {
    const r = totalPorPessoa([
        { valor: 12500, investido_por: 'Carlos' }, { valor: 12500, investido_por: 'Ana' },
        { valor: 2100, investido_por: 'Ana' }, { valor: 10, investido_por: null },
    ]);
    assert.deepEqual(r, [['Ana', 14600], ['Carlos', 12500], ['Sem registro', 10]]);
});

test('enderecoDeVolta só aceita http(s) no mesmo host (evita redirecionamento aberto)', () => {
    const host = '192.168.1.51';
    assert.equal(enderecoDeVolta('http://192.168.1.51:8081/', host), 'http://192.168.1.51:8081/');
    assert.equal(enderecoDeVolta('http://localhost:80/Projeto%20invest/web/', host), 'http://192.168.1.51/Projeto%20invest/web/');
    assert.equal(enderecoDeVolta('http://site-malicioso.com/', host), null);
    assert.equal(enderecoDeVolta('javascript:alert(1)', host), null);
    assert.equal(enderecoDeVolta('//192.168.1.51.evil.com/', host), null);
    assert.equal(enderecoDeVolta('', host), null);
    assert.equal(enderecoDeVolta(null, host), null);
});

// ------------------------------------------------------------------ metas de gastos
import { avaliarMeta, gastoNoPeriodo, historicoMeta, limiteVigente, periodoDe, situacao } from '../assets/js/dominio.js';

const despesas = [
    { data: '2026-10-01', valor: 300, categoria: 'Alimentação', status: 'Pago' },
    { data: '2026-10-02', valor: 200, categoria: 'Moradia', status: 'Pago' },
    { data: '2026-10-03', valor: 50, categoria: 'Alimentação', status: 'Pago' },
    { data: '2026-10-20', valor: 400, categoria: 'Alimentação', status: 'Previsto' },
    { data: '2026-09-15', valor: 900, categoria: 'Alimentação', status: 'Pago' },
    { data: '2025-05-10', valor: 1000, categoria: 'Transporte', status: 'Pago' },
];

test('periodoDe: dia, semana (domingo a sábado), mês (inclusive fevereiro e virada de ano) e ano', () => {
    assert.deepEqual(periodoDe('Diária', hoje), { inicio: '2026-10-03', fim: '2026-10-03', rotulo: '03/10', dias: 1 });
    assert.deepEqual(periodoDe('Diária', hoje, 3), { inicio: '2026-09-30', fim: '2026-09-30', rotulo: '30/09', dias: 1 });
    // 03/10/2026 é sábado: a semana vai de domingo 27/09 a sábado 03/10
    assert.deepEqual(periodoDe('Semanal', hoje), { inicio: '2026-09-27', fim: '2026-10-03', rotulo: '27/09 a 03/10', dias: 7 });
    assert.deepEqual(periodoDe('Semanal', hoje, 1), { inicio: '2026-09-20', fim: '2026-09-26', rotulo: '20/09 a 26/09', dias: 7 });
    assert.deepEqual(periodoDe('Semanal', new Date(2026, 9, 4)), { inicio: '2026-10-04', fim: '2026-10-10', rotulo: '04/10 a 10/10', dias: 7 });   // domingo começa semana nova
    assert.deepEqual(periodoDe('Semanal', new Date(2027, 0, 1)), { inicio: '2026-12-27', fim: '2027-01-02', rotulo: '27/12 a 02/01', dias: 7 });   // atravessa o ano
    assert.deepEqual(periodoDe('Mensal', hoje), { inicio: '2026-10-01', fim: '2026-10-31', rotulo: 'out/2026', dias: 31 });
    assert.deepEqual(periodoDe('Mensal', hoje, 8), { inicio: '2026-02-01', fim: '2026-02-28', rotulo: 'fev/2026', dias: 28 });
    assert.equal(periodoDe('Mensal', hoje, 10).rotulo, 'dez/2025');
    assert.deepEqual(periodoDe('Anual', hoje, 2), { inicio: '2024-01-01', fim: '2024-12-31', rotulo: '2024', dias: 366 });
});

test('gastoNoPeriodo: filtra período e categoria; previstas ficam à parte do gasto real', () => {
    assert.deepEqual(gastoNoPeriodo(despesas, 'Alimentação', '2026-10-01', '2026-10-31'), { pago: 350, previsto: 400 });
    assert.deepEqual(gastoNoPeriodo(despesas, null, '2026-10-01', '2026-10-31'), { pago: 550, previsto: 400 });
    assert.deepEqual(gastoNoPeriodo(despesas, 'Lazer', '2026-10-01', '2026-10-31'), { pago: 0, previsto: 0 });
});

test('limiteVigente usa a versão mais recente até o fim do período', () => {
    const versoes = [{ valor_limite: 1000, vigente_desde: '2026-01-01' }, { valor_limite: 1500, vigente_desde: '2026-10-10' }];
    assert.equal(limiteVigente(versoes, '2025-12-31'), null);   // antes da meta existir
    assert.equal(limiteVigente(versoes, '2026-09-30'), 1000);
    assert.equal(limiteVigente(versoes, '2026-10-31'), 1500);   // mudou no meio do mês: vale para o mês
});

test('situacao: verde abaixo de 80%, atenção até 100%, estourou acima', () => {
    assert.deepEqual([situacao(79), situacao(80), situacao(100), situacao(101)], ['ok', 'atencao', 'atencao', 'estourou']);
});

test('avaliarMeta mensal: real × meta, quanto falta, por dia e projeção do mês', () => {
    const r = avaliarMeta({ periodo: 'Mensal', categoria: 'Alimentação' }, [{ valor_limite: 1000, vigente_desde: '2026-01-01' }], despesas, hoje);
    assert.equal(r.gasto, 350);
    assert.equal(r.previsto, 400);
    assert.equal(r.pct, 35);
    assert.equal(r.restante, 650);
    assert.equal(r.situacao, 'ok');
    assert.equal(r.porDia.toFixed(2), (650 / 29).toFixed(2));        // 29 dias restantes contando hoje
    assert.equal(r.projecao.toFixed(2), (350 / 3 * 31).toFixed(2));   // 3 dias decorridos
});

test('avaliarMeta semanal estourada com projeção; período anterior sem projeção', () => {
    // semana 27/09 a 03/10: Alimentação pagas = 300 (01/10) + 50 (03/10); hoje é o 7º e último dia
    const semanal = avaliarMeta({ periodo: 'Semanal', categoria: 'Alimentação' }, [{ valor_limite: 280, vigente_desde: '2026-01-01' }], despesas, hoje);
    assert.deepEqual([semanal.gasto, semanal.pct, semanal.situacao, semanal.porDia, Math.round(semanal.projecao)], [350, 125, 'estourou', 0, 350]);
    const domingo = avaliarMeta({ periodo: 'Semanal', categoria: null }, [{ valor_limite: 700, vigente_desde: '2026-01-01' }], despesas, new Date(2026, 9, 4));
    assert.deepEqual([domingo.gasto, domingo.restante, domingo.porDia], [0, 700, 100]);   // 7 dias pela frente
    const setembro = avaliarMeta({ periodo: 'Mensal', categoria: 'Alimentação' }, [{ valor_limite: 1000, vigente_desde: '2026-01-01' }], despesas, hoje, 1);
    assert.deepEqual([setembro.rotulo, setembro.gasto, setembro.pct, setembro.situacao, setembro.projecao], ['set/2026', 900, 90, 'atencao', null]);
});

test('historicoMeta: 8 semanas, 6 meses ou 3 anos; cada período com o limite da época', () => {
    const versoes = [{ valor_limite: 800, vigente_desde: '2025-01-01' }, { valor_limite: 1200, vigente_desde: '2026-10-01' }];
    const anual = historicoMeta({ periodo: 'Anual', categoria: 'Transporte' }, versoes, despesas, hoje);
    assert.deepEqual(anual.map(r => [r.rotulo, r.limite, r.gasto]), [['2026', 1200, 0], ['2025', 800, 1000], ['2024', null, 0]]);
    assert.equal(historicoMeta({ periodo: 'Semanal' }, versoes, despesas, hoje).length, 8);
    assert.equal(historicoMeta({ periodo: 'Mensal' }, versoes, despesas, hoje).length, 6);
});

// ------------------------------------------------------------------ relatório de gastos
import { quemGastou, resumoGastos } from '../assets/js/dominio.js';

const nomes = { u1: 'Ana', u2: 'Carlos' };
const gastos = [
    { data: '2026-10-01', valor: 100, categoria: 'Alimentação', status: 'Pago', criado_por: 'u1' },
    { data: '2026-10-01', valor: 40, categoria: 'Lazer', status: 'Pago', criado_por: 'u1', gasto_por: 'Júlia' },
    { data: '2026-10-03', valor: 60, categoria: 'Alimentação', status: 'Pago', criado_por: 'u2' },
    { data: '2026-10-05', valor: 500, categoria: 'Moradia', status: 'Previsto', criado_por: 'u2' },
    { data: '2026-09-30', valor: 999, categoria: 'Moradia', status: 'Pago', criado_por: 'u2' },
    { data: '2026-11-02', valor: 70, categoria: 'Transporte', status: 'Pago', criado_por: null },
];

test('quemGastou: o informado na despesa, senão quem lançou', () => {
    assert.equal(quemGastou(gastos[1], nomes), 'Júlia');
    assert.equal(quemGastou(gastos[0], nomes), 'Ana');
    assert.equal(quemGastou(gastos[5], nomes), 'Sem registro');
});

test('resumoGastos do mês: total pago, previsto à parte, por pessoa, categoria e dia', () => {
    const r = resumoGastos(gastos, '2026-10-01', '2026-10-31', nomes);
    assert.deepEqual([r.total, r.previsto, r.quantidade], [200, 500, 3]);
    assert.deepEqual(r.porPessoa, [['Ana', 100], ['Carlos', 60], ['Júlia', 40]]);
    assert.deepEqual(r.porCategoria, [['Alimentação', 160], ['Lazer', 40]]);
    assert.deepEqual(r.linhaDoTempo.map(p => [p.chave, p.total, p.itens, p.pessoas]), [
        ['2026-10-03', 60, 1, [['Carlos', 60]]],
        ['2026-10-01', 140, 2, [['Ana', 100], ['Júlia', 40]]],
    ]);
});

test('resumoGastos do ano agrupa por mês', () => {
    const r = resumoGastos(gastos, '2026-01-01', '2026-12-31', nomes, true);
    assert.equal(r.total, 1269);
    assert.deepEqual(r.linhaDoTempo.map(p => [p.chave, p.total]), [['2026-11', 70], ['2026-10', 200], ['2026-09', 999]]);
});
