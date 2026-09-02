# CasaOrganizada — correção de integração com MySQL

## Problemas corrigidos

1. Dashboard passa a buscar receitas e despesas diretamente do MySQL.
2. Cards não usam valores fictícios.
3. Gráficos recebem JSON calculado no servidor.
4. Cadastro de despesa envia `forma_pagamento`, que era obrigatório no banco.
5. Cadastro usa `categoria_id`, compatível com a estrutura relacional.
6. `membro_id` fica `NULL`: não existe mais "Membro Responsável" no formulário.
7. Cartão é obrigatório somente quando a forma de pagamento é crédito.
8. Parcelamento usa transação PDO e cria as parcelas.
9. Anotações possuem tabela própria.
10. Exclusão de receitas/despesas usa prepared statements.
11. Dashboard tem filtro por mês/ano.
12. `valor_real` do banco é exposto também como `valor_realizado` para os gráficos.

## Instalação

1. Crie o banco executando `database/gestao_familiar_corrigido.sql` no phpMyAdmin.
2. Copie o projeto para `C:\wamp64\www\CasaOrganizada`.
3. Confira `config/conexao.php`:
   - host
   - banco
   - usuário
   - senha
4. Abra:
   `http://localhost/CasaOrganizada/index.php?route=dashboard`

## Observação sobre dados existentes

O SQL atual do projeto antigo contém uma tabela simples `gastos` e depois uma estrutura avançada `despesas`. Além disso, `receitas` aparece em duas versões. Não execute o SQL antigo por cima do banco atual sem backup.

A versão corrigida usa a estrutura relacional:

- receitas
- despesas
- parcelas
- categorias
- cartoes
- membros_familia
- anotacoes

O campo de membro é opcional no banco e não aparece mais no cadastro.

## Diagnóstico

Se o projeto ainda mostrar números zerados, o problema deixa de ser o gráfico e passa a ser:

- banco configurado errado;
- tabela sem registros;
- estrutura do banco diferente da migration;
- usuário do MySQL sem permissão.

O arquivo `config/conexao.php` centraliza essa conexão.

## Migração para Supabase

1. No painel do Supabase, abra o **SQL Editor** e execute
   `database/supabase_schema.sql`.
2. Em `/home/runner/work/CasaOrganizada/CasaOrganizada/config/conexao.php`
   ou nas variáveis de ambiente do servidor, configure:
   - `CASAORGANIZADA_DB_DRIVER=pgsql`
   - `CASAORGANIZADA_DB_HOST`
   - `CASAORGANIZADA_DB_PORT`
   - `CASAORGANIZADA_DB_NAME`
   - `CASAORGANIZADA_DB_USER`
   - `CASAORGANIZADA_DB_PASS`
   - `CASAORGANIZADA_DB_SSL_MODE=require`
3. Para conexão direta do Supabase, normalmente o banco é `postgres` e a porta
   é `5432`; no pooler, a porta costuma ser `6543`. Use exatamente os valores
   exibidos em **Connect** no painel do projeto.
4. A aplicação continua sendo um projeto PHP tradicional: o Supabase entra
   aqui como banco PostgreSQL hospedado. Você ainda precisa publicar o PHP em
   um servidor compatível com PHP 8.0+.
5. Depois de apontar para o Supabase, faça login e troque as senhas padrão dos
   usuários seedados.

## Publicando no WapServerOnline (ou outro hosting via FTP)

1. **Requisito mínimo**: PHP 8.0+ com extensões `pdo_mysql` e `mbstring` habilitadas.
   Se o painel do hosting não mostrar a versão, crie um arquivo temporário
   `phpinfo.php` na raiz com `<?php phpinfo();`, acesse-o pelo navegador para
   conferir a versão e **apague-o em seguida** (nunca deixe phpinfo() público).
2. Crie o banco de dados e o usuário MySQL pelo painel/phpMyAdmin do hosting,
   depois importe `database/gestao_familiar_corrigido.sql`.
3. Envie todo o conteúdo do projeto por FTP para a pasta pública do domínio
   (`public_html`, `www` ou equivalente). O `.htaccess` já bloqueia o acesso
   direto às pastas `config/`, `app/`, `database/` e `views/`.
4. `config/conexao.php` **não é enviado pelo git** (está no `.gitignore`).
   Copie `config/conexao.example.php` para `config/conexao.php` diretamente
   no servidor (via FTP ou gerenciador de arquivos) e preencha `DB_HOST`,
   `DB_NAME`, `DB_USER` e `DB_PASS` com os dados fornecidos pelo hosting.
5. Troque a senha do usuário `admin@casaorganizada.com` (padrão `admin123`)
   assim que conseguir logar — é uma senha de exemplo usada apenas em
   desenvolvimento.
6. Confirme que o site responde em HTTPS; a sessão só marca o cookie como
   seguro quando a requisição chega via HTTPS (`config/seguranca.php`).
7. Erros do PHP não aparecem mais na tela em produção (ver topo de
   `index.php`); consulte o log de erros do hosting caso algo falhe.

## Protegendo o acesso ao banco de dados

1. **Nunca use o usuário `root`** em produção. No painel do hosting, crie um
   usuário de banco dedicado só para esta aplicação, com privilégios apenas
   sobre o banco `gestao_familiar` (SELECT, INSERT, UPDATE, DELETE, CREATE,
   ALTER) — evite `GRANT ALL` global.
2. Use uma senha forte e aleatória (bem diferente da senha `4605` usada em
   desenvolvimento local), por exemplo:
   `b1452c9addfb1f08e28072f2` (gerada agora só como exemplo — gere a sua e
   guarde em um cofre de senhas, não em chat ou anotações).
3. A maioria dos hostings compartilhados já restringe o MySQL a conexões
   `localhost` (o próprio servidor). Confirme essa opção no painel e **não
   habilite "acesso remoto ao banco"** a menos que seja estritamente
   necessário; se precisar, restrinja por IP.
4. Mantenha `config/conexao.php` fora do git (já está no `.gitignore`) e
   com permissão de leitura restrita no servidor (via FTP, ajuste a
   permissão do arquivo para `640` ou o mais restritivo que o hosting
   permitir).
5. Ative backups automáticos do banco pelo painel do hosting (ou exporte
   periodicamente pelo phpMyAdmin) antes de qualquer alteração maior.
6. Troque as senhas padrão de `admin@casaorganizada.com` e
   `matheusviniciuscaieiras@gmail.com` assim que o primeiro login funcionar.
