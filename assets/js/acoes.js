// Ações do usuário: formulários e cliques (delegação de eventos no documento).
import { backend } from './backend.js';
import { calGoToday, calIrPara, calNav, calSelectDay } from './calendar.js';
import { db, demo, DEMO_MSG, estado, today } from './contexto.js';
import { ativosInvest } from './paginas/investimentos.js';
import { paginaDaUrl, render } from './roteador.js';
import { carregarInvest, definirUsuario, entrar, recarregar } from './sincronizacao.js';
import { lerFormulario, toast } from './ui.js';

const numeroOuNulo = valor => (valor === '' || valor === undefined ? null : Number(valor));

// Formulários de cadastro simples: ação -> [coleção, função que monta o registro].
const CADASTROS = {
    'add-receita': ['receitas', d => ({ descricao: d.descricao, categoria: d.categoria, valor: Number(d.valor), data: d.data, tipo: d.tipo, status: d.status, observacao: d.observacao || null })],
    'add-despesa': ['despesas', d => ({ descricao: d.descricao, categoria: d.categoria, valor: Number(d.valor), data: d.data, pagamento: d.pagamento, gasto_por: d.gasto_por || null, tipo: d.tipo, status: d.status, observacao: d.observacao || null })],
    'add-cartao': ['cartoes', d => ({ nome: d.nome, banco: d.banco || null, limite: Number(d.limite || 0), vencimento: Number(d.vencimento || 0) || null, responsavel: d.responsavel || 'Sem responsável' })],
    'add-membro': ['membros', d => ({ nome: d.nome, parentesco: d.parentesco || null })],
    'add-parcelamento': ['parcelamentos', d => ({ descricao: d.descricao, valor_total: Number(d.valorTotal), parcelas: Number(d.parcelas), data: d.data, cartao: d.cartao })],
    'add-nota': ['notas', d => ({ titulo: d.titulo, descricao: d.descricao || null, data: d.data, tipo: d.tipo })],
    'add-objetivo': ['objetivos', d => ({ nome: d.nome, categoria: d.categoria, valor_meta: numeroOuNulo(d.valor_meta), valor_atual: Number(d.valor_atual || 0), prazo: d.prazo || null })],
};

// Formulários de edição: ação -> [coleção, função que monta as alterações].
const EDICOES = {
    'editar-membro': ['membros', d => ({ nome: d.nome, parentesco: d.parentesco || null })],
    'editar-objetivo': ['objetivos', d => ({ valor_meta: numeroOuNulo(d.valor_meta), prazo: d.prazo || null, valor_atual: Number(d.valor_atual || 0) })],
};

/** Executa uma ação com o botão travado; mostra o resultado e redesenha. */
async function executar(formEl, tarefa, sucesso) {
    const botao = formEl?.querySelector('button[type="submit"]');
    if (botao) botao.disabled = true;
    try {
        const r = await tarefa();
        await recarregar();
        toast(r?.resumo || sucesso);
        render();
    } catch (err) {
        toast('Não foi possível salvar: ' + err.message);
        if (botao) botao.disabled = false;
    }
}

async function fazerLogin(dados) {
    let user;
    try { user = await backend.signIn(dados.email, dados.senha); }
    catch (err) { toast('Não foi possível entrar: ' + err.message); return; }
    await entrar(user);
}

async function fazerCadastro(dados) {
    let user;
    try { user = await backend.signUp(dados.nome, dados.email, dados.senha); }
    catch (err) { toast('Não foi possível criar a conta: ' + err.message); return; }
    if (user) await entrar(user);
    else { toast('Conta criada! Verifique seu e-mail para confirmar antes de entrar.'); estado.authMode = 'login'; render(); }
}

/** Lançamentos dos objetivos: dinheiro guardado, compra no invest ou vínculo de ativo. */
function lancarObjetivo(formEl, acao, d) {
    return executar(formEl, async () => {
        let r;
        if (acao === 'aporte-objetivo') {
            const valor = Number(d.valor);
            if (!(valor > 0)) throw new Error('informe um valor maior que zero');
            if (backend.objetivoAporte) r = await backend.objetivoAporte({ objetivo_id: d.objetivo_id, tipo: 'Dinheiro', valor, data: today });
            else {
                const item = db.objetivos.find(o => o.id === d.objetivo_id);
                await backend.updateRow('objetivos', item.id, { valor_atual: Math.round((Number(item.valor_atual) + valor) * 100) / 100 });
            }
        }
        if (acao === 'invest-objetivo') {
            toast('Registrando a compra no Projeto invest…');
            r = await backend.objetivoAporte({
                objetivo_id: d.objetivo_id, tipo: 'Investimento', ticker: d.ticker.trim().toUpperCase(), quantidade: Number(d.quantidade),
                preco: Number(d.preco), custos: d.custos ? Number(d.custos) : 0, data: d.data, tipo_ativo: d.tipo_ativo,
            });
        }
        if (acao === 'vincular-objetivo') r = await backend.objetivoVincular(d.objetivo_id, d.ticker, true);
        if (acao !== 'aporte-objetivo') await carregarInvest();
        return r;
    }, 'Objetivo atualizado.');
}

/** Cria ou edita uma meta de gastos (mudar o limite acrescenta uma versão ao histórico). */
function salvarMeta(formEl, d) {
    if (!backend.metaSalvar) return toast('Metas de gastos só funcionam no uso local (MySQL).');
    return executar(formEl, () => backend.metaSalvar({
        id: d.id || '', nome: d.nome, periodo: d.periodo, categoria: d.categoria, nota: d.nota || '', valor_limite: d.valor_limite,
        vigente_desde: d.vigente_desde || today, nota_alteracao: d.nota_alteracao || '', ...(d.id ? { ativa: d.ativa === 'on' } : {}),
    }), 'Meta salva.');
}

/** Cria ou edita uma conta de acesso. Se for a própria conta, atualiza o nome no topo. */
function salvarConta(formEl, d) {
    return executar(formEl, async () => {
        const r = await backend.usuarioSalvar({ id: d.id, nome: d.nome, email: d.email, senha: d.senha || '', senha_atual: d.senha_atual || '', invest: d.invest === 'on', admin: d.admin === 'on' });
        if (d.id === estado.session.id) definirUsuario(await backend.getUser());
        return r;
    }, 'Conta salva.');
}

async function aoEnviar(event) {
    event.preventDefault();
    const formEl = event.target, acao = formEl.dataset.form, d = lerFormulario(formEl);
    if (acao === 'login') return fazerLogin(d);
    if (acao === 'signup') return fazerCadastro(d);
    if (demo) return toast(DEMO_MSG);
    if (['aporte-objetivo', 'invest-objetivo', 'vincular-objetivo'].includes(acao)) return lancarObjetivo(formEl, acao, d);
    if (acao === 'usuario-novo' || acao === 'usuario-editar') return salvarConta(formEl, d);
    if (acao === 'meta-nova' || acao === 'meta-editar') return salvarMeta(formEl, d);
    if (CADASTROS[acao]) {
        const [colecao, montar] = CADASTROS[acao];
        return executar(formEl, () => backend.insertRow(colecao, { ...montar(d), criado_por: estado.session.id }), 'Registro salvo com sucesso.');
    }
    if (EDICOES[acao]) {
        const [colecao, montar] = EDICOES[acao];
        return executar(formEl, () => backend.updateRow(colecao, d.id, montar(d)), 'Registro atualizado.');
    }
}

async function aoClicar(event) {
    const alvo = seletor => event.target.closest(seletor);

    if (alvo('[data-action="toggle-auth"]')) { event.preventDefault(); estado.authMode = estado.authMode === 'signup' ? 'login' : 'signup'; render(); return; }
    if (alvo('[data-action="menu"]')) { estado.menuAberto = !estado.menuAberto; render(); return; }
    if (alvo('[data-action="tema"]')) { window.alternarTema?.(); render(); return; }
    if (alvo('[data-action="invest-sem-acesso"]')) { toast('Sua conta não tem acesso aos investimentos. Peça ao administrador para liberar.'); return; }
    if (alvo('[data-action="logout"]')) { await backend.signOut(); return; }

    const periodoGastos = alvo('[data-periodo-gastos]');
    if (periodoGastos) { estado.periodoGastos = periodoGastos.dataset.periodoGastos; render(); return; }
    const irDia = alvo('[data-ir-dia]');
    if (irDia) { calIrPara(irDia.dataset.irDia); if (location.hash === '#/calendario') render(); return; }   // o link já navega para #/calendario

    const calNavBtn = alvo('[data-cal-nav]');
    if (calNavBtn) { const delta = Number(calNavBtn.dataset.calNav); if (delta === 0) calGoToday(); else calNav(delta); render(); return; }
    const calDayBtn = alvo('[data-cal-day]');
    if (calDayBtn) { calSelectDay(calDayBtn.dataset.calDay); render(); return; }

    if (demo && alvo('[data-delete], [data-toggle], [data-pay], [data-vincular], [data-excluir-usuario], [data-excluir-meta]')) { toast(DEMO_MSG); return; }

    const excluirConta = alvo('[data-excluir-usuario]');
    if (excluirConta) {
        if (!confirm(`Excluir o acesso de ${excluirConta.dataset.nome}? A pessoa não conseguirá mais entrar.`)) return;
        return executar(null, () => backend.usuarioExcluir(excluirConta.dataset.excluirUsuario), 'Acesso excluído.');
    }

    const excluirMeta = alvo('[data-excluir-meta]');
    if (excluirMeta) {
        if (!confirm(`Excluir a meta "${excluirMeta.dataset.nome}" e o histórico de limites dela? As despesas não são apagadas.`)) return;
        return executar(null, () => backend.metaExcluir(excluirMeta.dataset.excluirMeta), 'Meta excluída.');
    }

    const vinculo = alvo('[data-vincular]');
    if (vinculo) {
        const [objetivoId, ticker, liga] = vinculo.dataset.vincular.split(':');
        return executar(null, () => backend.objetivoVincular(objetivoId, ticker, liga === '1'), liga === '1' ? `${ticker} vinculado.` : `${ticker} desvinculado.`);
    }

    const excluir = alvo('[data-delete]');
    if (excluir) {
        const [colecao, id] = excluir.dataset.delete.split(':');
        if (colecao === 'objetivos' && !confirm('Excluir este objetivo e todo o histórico dele?')) return;
        return executar(null, () => backend.deleteRow(colecao, id), 'Registro removido.');
    }

    const alternar = alvo('[data-toggle]');
    if (alternar) {
        const [colecao, id, campo, bruto] = alternar.dataset.toggle.split(':');
        const valor = bruto === 'true' ? true : bruto === 'false' ? false : bruto;
        return executar(null, () => backend.updateRow(colecao, id, { [campo]: valor }), 'Atualizado.');
    }

    const pagar = alvo('[data-pay]');
    if (pagar) {
        const item = db.parcelamentos.find(row => row.id === pagar.dataset.pay);
        if (item && item.pagas < item.parcelas) return executar(null, () => backend.updateRow('parcelamentos', item.id, { pagas: item.pagas + 1 }), 'Parcela marcada como paga.');
    }
}

/** Ao digitar um ticker conhecido, sugere o preço atual dele no campo de preço. */
function aoDigitar(event) {
    const campoTicker = event.target.closest('[data-ticker-input]');
    if (!campoTicker) return;
    const ativo = ativosInvest().find(a => a.ticker === campoTicker.value.trim().toUpperCase());
    const preco = campoTicker.form.querySelector('[name="preco"]');
    if (ativo && preco && !preco.value) preco.value = ativo.preco.toFixed(2);
}

function aoNavegar() {
    estado.menuAberto = false;
    if (!estado.session) { estado.page = 'login'; render(); return; }
    estado.page = paginaDaUrl();
    render();
}

export function registrarEventos() {
    document.addEventListener('submit', aoEnviar);
    document.addEventListener('click', aoClicar);
    document.addEventListener('input', aoDigitar);
    window.addEventListener('hashchange', aoNavegar);
}
