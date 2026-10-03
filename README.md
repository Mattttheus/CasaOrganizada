# Casa Organizada

Aplicativo SPA (HTML + CSS + JavaScript puro, sem build) para organizar as
finanças da família: receitas, despesas, cartões, parcelamentos, calendário,
**objetivos e metas** (com reservas de emergência e investimentos vinculados ao
Projeto invest) e os acessos da família.

Três backends, escolhidos automaticamente (`BACKEND = 'auto'` em `assets/js/config.js`):

- **Rede local (WampServer)** — API PHP em `api/` + MySQL. É o uso principal.
- **GitHub Pages / Vercel** — modo **vitrine**, com dados fictícios e nada é salvo.
- **Supabase** (opcional, `BACKEND = 'supabase'`) — Postgres + Auth na nuvem.

## Arquitetura

```
index.html                  entrada única (rotas por hash: #/pagina)
assets/css/static.css       visual (tokens de cor claro/escuro compartilhados com o Projeto invest)
assets/js/
  app.js                    inicialização
  backend.js                escolhe o backend: localApi.js (MySQL) | data.js (Supabase) | demo.js (vitrine)
  contexto.js               estado compartilhado (dados, sessão, página) e constantes
  dominio.js                regras de negócio puras (progresso das metas, totais, endereço de volta)
  ui.js                     componentes de interface (formulários, avisos, botão de tema)
  paginas/                  uma página por arquivo: layout, acesso, financas, objetivos, familia, investimentos
  roteador.js               desenha a página da rota atual
  sincronizacao.js          carrega os dados e controla entrada/saída da sessão
  acoes.js                  formulários e cliques
  tema.js                   tema claro/escuro (mesmo arquivo no Projeto invest)
api/
  index.php                 roteador: ?acao= -> [método HTTP, função]
  nucleo/                   infraestrutura: http, banco, sessao, limites, validacao, invest (ponte)
  modulos/                  regras de negócio: autenticacao, colecoes, objetivos, usuarios, investimentos
  config.php                conexão (credenciais reais em config.local.php, fora do git)
database/mysql.sql          schema MySQL (idempotente: pode importar de novo)
database/supabase.sql       schema + RLS do Supabase
tests/                      testes automatizados (veja "Testes")
```

Dependências apontam para dentro: páginas usam `dominio.js`/`ui.js`, nunca o
contrário; na API, `modulos/` usa `nucleo/`. Arquivos de `api/nucleo` e
`api/modulos` não são acessíveis pela web.

## 1. Criar e configurar o projeto Supabase

1. Crie uma conta e um projeto gratuito em https://app.supabase.com.
2. Abra **SQL Editor** no projeto e rode o conteúdo de
   [`database/supabase.sql`](database/supabase.sql) (pode rodar de novo sem
   problemas, os comandos são idempotentes).
3. Em **Settings > API**, copie a **Project URL** e a chave **anon public**.
4. Cole os dois valores em [`assets/js/config.js`](assets/js/config.js):
   ```js
   export const SUPABASE_URL = 'https://xxxxxxxx.supabase.co';
   export const SUPABASE_ANON_KEY = 'eyJhbGciOi...';
   ```
5. (Opcional) Em **Authentication > Providers > Email**, desative "Confirm
   email" se quiser que o cadastro libere login imediato, sem clicar num link
   de confirmação enviado por e-mail.

> A chave **anon public** não é secreta — ela é feita para rodar no
> navegador. Quem protege os dados é o Row Level Security (RLS) já criado
> pelo `supabase.sql`. **Nunca** use a chave `service_role` no front-end.

## Modos de funcionamento

Com `BACKEND = 'auto'` (padrão em `assets/js/config.js`):

- **localhost / rede local (192.168.x.x)** — app completo com MySQL do WAMP.
- **GitHub Pages / Vercel** — **modo vitrine**: entra direto como "Visitante"
  com dados fictícios (`assets/js/demo.js`); cadastrar, editar e excluir
  ficam desativados. Para ter dados reais online, use `BACKEND = 'supabase'`.

## 2. Rodar localmente com WampServer (MySQL)

Na rede local o app não precisa do Supabase: ao abrir em `localhost` ou num IP
`192.168.x.x` ele usa a API PHP em `api/index.php` + MySQL do WAMP.

1. Abra o phpMyAdmin (http://localhost/phpmyadmin), vá em **Importar** e
   envie [`database/mysql.sql`](database/mysql.sql). Ele cria o banco
   `casa_organizada` com todas as tabelas, **vazias** (nenhum dado lançado vai
   para o repositório).
2. Crie um usuário do MySQL só para a API (não use o root) e coloque as
   credenciais em `api/config.local.php` (fora do git):
   ```sql
   CREATE USER 'casa_app'@'localhost' IDENTIFIED BY 'senha-forte';
   CREATE USER 'casa_app'@'127.0.0.1' IDENTIFIED BY 'senha-forte';
   GRANT SELECT, INSERT, UPDATE, DELETE ON casa_organizada.* TO 'casa_app'@'localhost', 'casa_app'@'127.0.0.1';
   ```
   ```php
   <?php return ['user' => 'casa_app', 'pass' => 'senha-forte'];
   ```
3. Crie o primeiro administrador (não há cadastro aberto; o mesmo comando
   recupera o acesso se a senha for esquecida):
   ```bash
   php database/criar_admin.php "Seu nome" seu@email.com "senha-com-10+-caracteres"
   ```
4. Acesse o app pelo IP fixo do servidor (VirtualHost dedicado na porta 8082) e
   cadastre a família em **Família → Acessos ao sistema**.

### Login único com o Projeto invest

O Casa Organizada faz o login dos dois projetos. O Projeto invest
(`http://IP:8081`) só abre para quem está logado aqui **e** tem acesso aos
investimentos (marcado pelo administrador na página Família).

- O invest valida a sessão chamando `api/index.php?acao=sessao` pelo próprio PC.
- O painel mostra **Renda de investimentos** e os objetivos somam o valor de
  mercado dos ativos vinculados (`resumo.php` do invest via `acao=invest_resumo`).
- "Investir pelo Projeto invest" num objetivo registra a compra no invest
  (mesmas regras do Cadastro) e vincula o ativo ao objetivo.

### Segurança

- **Login:** mensagem única para e-mail inexistente e senha errada (não revela
  contas) com o mesmo tempo de resposta; 5 senhas erradas por e-mail (20 por
  aparelho) bloqueiam por 15 min; novo ID de sessão a cada login.
- **Sessão:** cookie `HttpOnly` + `SameSite=Strict`, expira após 8 h sem uso
  ou 24 h desde o login; permissões relidas do banco a cada pedido.
- **Contas:** só o administrador cria/edita/exclui acessos; senhas com 8+
  caracteres, letras e números; trocar a própria senha exige a atual (com
  limite de tentativas); o sistema nunca fica sem administrador.
- **API:** só aceita JSON, recusa POST de outros sites (Origin), valida todos os
  campos no servidor, SQL só com parâmetros e nomes de tabela fixos, erros do
  banco nunca vão para o navegador; usuário MySQL com permissão mínima.
- **Apache:** CSP, `X-Frame-Options`, `nosniff`, versões escondidas, TRACE
  desligado; `.git`, `database/`, `tests/`, `api/nucleo|modulos` e
  configurações bloqueados; acesso só deste PC e da rede 192.168.1.x.

## Testes

Só precisam do Node (sem `npm install`):

```bash
npm test                 # tudo
npm run test:dominio     # regras de negócio do front-end
npm run test:api         # API completa num banco separado (casa_organizada_teste), com php -S
npm run test:seguranca   # auditoria só-leitura do servidor real (cabeçalhos, bloqueios, login)
```

`test:api` recria `casa_organizada_teste` a partir de `database/mysql.sql`,
sobe um servidor PHP na porta 8099 e apaga o banco de teste no fim: os dados
reais não são tocados. Caminhos do WAMP: variáveis `CASA_PHP` e `CASA_MYSQL`;
host da auditoria: `CASA_HOST` (padrão 192.168.1.51).

## 3. Publicar no GitHub Pages

1. Faça commit e push dos arquivos para o GitHub.
2. No repositório, abra **Settings > Pages**.
3. Em **Build and deployment**, selecione **Deploy from a branch**.
4. Escolha a branch `main` e a pasta `/ (root)`, depois clique em **Save**.
5. Acesse a URL exibida pelo GitHub, normalmente
   `https://mattttheus.github.io/CasaOrganizada/`.

## 4. Publicar na Vercel

1. Em https://vercel.com, **Add New > Project** e importe este repositório
   do GitHub.
2. Framework Preset: **Other**. Não há build command nem install command —
   deixe os campos em branco (é um site estático).
3. Output Directory: raiz do projeto (padrão).
4. Clique em **Deploy**. O `vercel.json` já define cache dos assets; como o
   roteamento é por hash (`#/pagina`), não é preciso configurar rewrites.
5. A cada push para `main`, a Vercel publica um novo deploy automaticamente.

## Páginas disponíveis

- **Visão geral** — saldo, metas de gastos, renda de investimentos e objetivos.
- **Receitas** e **Despesas** — a despesa registra **quem gastou**; a página traz o
  relatório de gastos de **hoje, da semana, do mês e do ano** (comparado com a meta geral),
  por pessoa, por categoria e dia a dia (cada dia abre no calendário).
- **Calendário** — quanto foi gasto em cada dia e por quem, contas a pagar/receber,
  notas e tarefas.
- **Metas de gastos** — limites **semanais (domingo a sábado), mensais e anuais** (gerais ou por
  categoria) × gasto real, projeção do período e quanto ainda dá para gastar por
  dia; cada mudança de limite fica no histórico com nota e autor, e os períodos
  antigos são comparados com o limite que valia neles.
- **Objetivos** — metas de dinheiro (casa, carro, viagens, reservas de emergência…)
  com dinheiro guardado, investimentos do Projeto invest e quem investiu.
- **Cartões**, **Parcelamentos** e **Família** (acessos ao sistema e membros).

Dados pessoais e lançamentos ficam só no banco local: o repositório (e o
GitHub Pages) leva apenas o código, a estrutura do banco e os dados fictícios
da vitrine.
