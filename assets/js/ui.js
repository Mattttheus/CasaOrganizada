// Componentes de interface reutilizáveis (geram HTML) e avisos. Todo texto vindo de dados
// passa por esc() antes de entrar no HTML.
import { esc } from './helpers.js';
import { situacao } from './dominio.js';

export const form = (fields, submit, title) => `<section class="card"><div class="card-head"><h2>${title}</h2></div><div class="card-body"><form data-form="${submit}" class="form-grid">${fields}<div class="field full"><button class="btn btn-primary" type="submit">Salvar</button></div></form></div></section>`;

export const field = (label, name, type = 'text', extra = '') => `<div class="field"><label for="${name}">${label}</label><input id="${name}" name="${name}" type="${type}" ${extra}></div>`;

export const select = (label, name, options) => `<div class="field"><label for="${name}">${label}</label><select id="${name}" name="${name}">${options.map(option => `<option>${esc(option)}</option>`).join('')}</select></div>`;

/** field() com id único (vários formulários iguais na mesma página, um por registro). */
export const campo = (id, rotulo, nome, tipo, extra = '') => field(rotulo, nome, tipo, extra).replace(new RegExp(`(id|for)="${nome}"`, 'g'), `$1="${nome}-${id}"`);

export const marcado = (rotulo, nome, ligado) => `<label class="check"><input type="checkbox" name="${nome}" ${ligado ? 'checked' : ''}> ${rotulo}</label>`;

/** Botão ☾/☀ do tema claro/escuro (assets/js/tema.js, compartilhado com o Projeto invest). */
export const temaBtn = (extra = '') => {
    const escuro = window.temaAtual?.() === 'dark';
    const titulo = escuro ? 'Usar tema claro' : 'Usar tema escuro';
    return `<button class="btn btn-tema ${extra}" data-action="tema" title="${titulo}" aria-label="${titulo}">${escuro ? '☀' : '☾'}</button>`;
};

/**
 * Classe de cor pela situação — o mesmo padrão em todos os painéis:
 * 'ok' verde, 'atencao' amarelo, 'estourou' vermelho, 'neutro' azul (informativo).
 */
export const classeStatus = status => `status-${status || 'neutro'}`;

/** Situação de um gasto em relação a um limite (verde < 80%, amarelo até 100%, vermelho acima). */
export const statusDoUso = (usado, limite) => (limite > 0 ? situacao(Math.round(usado / limite * 100)) : 'neutro');

/** Cartão de métrica com a borda na cor da situação. */
export const metrica = (rotulo, valor, status = 'neutro', extra = '') => `<div class="card metric ${classeStatus(status)}"><div class="metric-label">${rotulo}</div><div class="metric-value">${valor}</div>${extra}</div>`;

export const lerFormulario = formEl => Object.fromEntries(new FormData(formEl).entries());

export function toast(message) {
    const el = document.createElement('div');
    el.className = 'toast';
    el.setAttribute('role', 'status');
    el.textContent = message;
    document.body.append(el);
    setTimeout(() => el.remove(), 3000);
}
