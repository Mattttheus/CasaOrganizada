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
