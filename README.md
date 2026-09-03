# Casa Organizada

Aplicativo estático (HTML + CSS + JavaScript) para organizar as finanças da família, pronto para publicar no **GitHub Pages** — sem PHP, Apache ou banco de dados.

## Como funciona

- `index.html` é a única página de entrada.
- `assets/css/static.css` cuida de todo o visual.
- `assets/js/app.js` implementa a navegação (login, dashboard, receitas, despesas, cartões, parcelamentos e membros) e guarda os dados no `localStorage` do navegador.
- Cada navegador/dispositivo mantém seus próprios dados — não há servidor nem sincronização entre usuários.

## Publicar no GitHub Pages

1. Faça commit e push dos arquivos para o GitHub.
2. No repositório, abra **Settings > Pages**.
3. Em **Build and deployment**, selecione **Deploy from a branch**.
4. Escolha a branch `main` e a pasta `/ (root)`, depois clique em **Save**.
5. Acesse a URL exibida pelo GitHub, normalmente
   `https://mattttheus.github.io/CasaOrganizada/`.

## Rodar localmente

Basta abrir `index.html` em um navegador, ou servir a pasta com qualquer servidor estático, por exemplo:

```bash
npx serve .
```

## Estrutura do projeto

```
index.html            # ponto de entrada
assets/css/static.css # estilos
assets/js/app.js      # lógica da aplicação (SPA com localStorage)
database/             # esquemas SQL de referência (não usados pela versão estática)
```

## Evoluindo para múltiplos usuários

Para compartilhar dados entre usuários, a camada `localStorage` deverá ser
substituída por uma API pública, como Supabase Auth + RLS; chaves secretas do
Supabase nunca devem ser colocadas em JavaScript publicado.
