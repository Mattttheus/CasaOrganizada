// Tema claro/escuro compartilhado pelo Casa Organizada e pelo Projeto invest
// (mesmo arquivo nos dois projetos). A escolha fica no cookie "tema", que vale
// para todas as portas do mesmo endereço; sem escolha, segue o sistema.
// Script comum (não módulo) carregado no <head> para aplicar o tema antes de
// desenhar a página, sem piscar.
(function () {
    var raiz = document.documentElement;
    var salvo = document.cookie.match(/(?:^|;\s*)tema=(claro|escuro)/);
    if (salvo) raiz.dataset.theme = salvo[1] === 'escuro' ? 'dark' : 'light';

    window.temaAtual = function () {
        return raiz.dataset.theme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    };

    // Troca o tema, grava a escolha e avisa a página (evento "tema") para redesenhar o que precisar.
    window.alternarTema = function () {
        var novo = window.temaAtual() === 'dark' ? 'light' : 'dark';
        raiz.classList.add('tema-transicao');
        raiz.dataset.theme = novo;
        document.cookie = 'tema=' + (novo === 'dark' ? 'escuro' : 'claro') + ';path=/;max-age=31536000;SameSite=Lax';
        setTimeout(function () { raiz.classList.remove('tema-transicao'); }, 400);
        document.dispatchEvent(new CustomEvent('tema', { detail: novo }));
        return novo;
    };
})();
