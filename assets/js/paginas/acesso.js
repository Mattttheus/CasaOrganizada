// Telas fora do login: entrar (e cadastro no modo Supabase), carregando e configuração pendente.
import { mode } from '../backend.js';
import { estado } from '../contexto.js';
import { field, temaBtn } from '../ui.js';

const marca = '<div class="brand" style="padding:0 0 25px;color:var(--green-dark)"><span class="brand-mark">⌂</span> Casa Organizada</div>';

export function configWarning() {
    return `<main class="login">${temaBtn('login-tema')}<section class="card login-box">${marca}
        <h1>Configuração pendente</h1>
        <p>Edite <code>assets/js/config.js</code> com a URL e a chave anon do seu projeto Supabase, depois rode <code>database/supabase.sql</code> no SQL Editor do projeto.</p>
        <p>Para rodar localmente com o MySQL do WampServer, importe <code>database/mysql.sql</code> pelo phpMyAdmin e abra o app em <code>localhost</code>.</p>
    </section></main>`;
}

export const loadingScreen = () => '<main class="login"><section class="card login-box" style="text-align:center"><p>Carregando…</p></section></main>';

export function login() {
    // No uso local (MySQL) as contas são criadas pelo administrador na página Família.
    const podeCadastrar = mode === 'supabase';
    const isSignup = podeCadastrar && estado.authMode === 'signup';
    const rodape = podeCadastrar
        ? `${isSignup ? 'Já tem conta?' : 'Ainda não tem conta?'} <a href="#" data-action="toggle-auth">${isSignup ? 'Entrar' : 'Criar conta'}</a>`
        : 'Não tem acesso? Peça ao administrador da família para cadastrar você.';
    return `<main class="login">${temaBtn('login-tema')}<section class="card login-box">${marca}
        <h1>${isSignup ? 'Crie sua conta' : 'Suas finanças, em ordem.'}</h1>
        <p>${isSignup ? 'Cadastre-se para cuidar da casa junto com a família.' : 'Entre para cuidar da casa com mais clareza.'}</p>
        <form data-form="${isSignup ? 'signup' : 'login'}">
            ${isSignup ? field('Nome', 'nome', 'text', 'required placeholder="Seu nome"') : ''}
            ${field('E-mail', 'email', 'email', 'required autocomplete="username" placeholder="familia@exemplo.com"')}
            ${field('Senha', 'senha', 'password', 'required minlength="6" autocomplete="current-password" placeholder="••••••••"')}
            <button class="btn btn-primary" style="width:100%;margin-top:8px" type="submit">${isSignup ? 'Criar conta' : 'Entrar'}</button>
        </form>
        <small style="display:block;color:var(--muted);margin-top:18px">${rodape}</small>
    </section></main>`;
}
