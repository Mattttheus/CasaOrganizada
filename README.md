# Casa Organizada

Aplicativo SPA (HTML + CSS + JavaScript puro, sem build) para organizar as
finanças da família, com **Supabase** (Postgres + Auth) como backend. Roda
tanto no **GitHub Pages** quanto na **Vercel**, sem servidor próprio.

## Como funciona

- `index.html` é a única página de entrada (roteamento por hash, `#/pagina`).
- `assets/css/static.css` cuida de todo o visual.
- `assets/js/` contém a lógica da SPA, dividida em módulos ES:
  - `config.js` — URL e chave anon do seu projeto Supabase (edite este arquivo).
  - `supabaseClient.js` — cria o cliente Supabase (via CDN, sem npm/build).
  - `data.js` — leitura/escrita das tabelas (receitas, despesas, cartões,
    parcelamentos, membros, notas/tarefas).
  - `calendar.js` — página de Calendário (notas, tarefas, contas a pagar e a
    receber por data).
  - `app.js` — login/cadastro, navegação e as demais páginas.
- Login e dados ficam no Supabase (Auth + Postgres com Row Level Security);
  qualquer pessoa da família autenticada vê e edita os mesmos registros.
- `database/supabase.sql` é o schema a rodar no seu projeto Supabase.

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

## 2. Rodar localmente

Abra `index.html` direto no navegador, ou sirva a pasta com qualquer
servidor estático (alguns navegadores bloqueiam módulos ES via `file://`):

```bash
npx serve .
```

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

Dashboard, Receitas, Despesas, Cartões, Parcelamentos, **Calendário** (notas,
tarefas e datas de contas a pagar/a receber, navegável por mês) e Família.

## Estrutura do projeto

```
index.html              # ponto de entrada
assets/css/static.css   # estilos
assets/js/config.js     # credenciais do Supabase (edite aqui)
assets/js/supabaseClient.js
assets/js/data.js
assets/js/calendar.js
assets/js/app.js
database/supabase.sql   # schema + RLS para rodar no Supabase
vercel.json             # configuração de deploy na Vercel
```

`database/gestao_familiar_corrigido.sql` é o schema MySQL da versão antiga
com backend PHP (removida) — mantido só como referência histórica.
