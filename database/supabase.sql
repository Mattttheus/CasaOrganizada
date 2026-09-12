-- Casa Organizada — schema Supabase (PostgreSQL)
-- Execute no SQL Editor do seu projeto em https://app.supabase.com
-- Pode rodar quantas vezes quiser: todos os comandos são idempotentes.
--
-- Modelo de dados: "casa compartilhada" — qualquer usuário autenticado
-- (família toda) lê e escreve os mesmos registros. A autenticação usa o
-- Supabase Auth (auth.users); não guardamos senha em nenhuma tabela nossa.

create extension if not exists "pgcrypto";

-- ---------------------------------------------------------------------------
-- Perfis (espelha auth.users com nome de exibição)
-- ---------------------------------------------------------------------------
create table if not exists perfis (
    id uuid primary key references auth.users (id) on delete cascade,
    nome varchar(100) not null,
    email varchar(150) not null,
    criado_em timestamptz not null default now()
);

-- cria automaticamente um perfil quando alguém se cadastra pelo app
create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer set search_path = public
as $$
begin
    insert into public.perfis (id, nome, email)
    values (
        new.id,
        coalesce(new.raw_user_meta_data ->> 'nome', split_part(new.email, '@', 1)),
        new.email
    )
    on conflict (id) do nothing;
    return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
    after insert on auth.users
    for each row execute procedure public.handle_new_user();

-- ---------------------------------------------------------------------------
-- Tabelas da aplicação
-- ---------------------------------------------------------------------------
create table if not exists membros_familia (
    id uuid primary key default gen_random_uuid(),
    nome varchar(100) not null,
    parentesco varchar(50),
    criado_por uuid references auth.users (id) on delete set null,
    criado_em timestamptz not null default now()
);

create table if not exists cartoes (
    id uuid primary key default gen_random_uuid(),
    nome varchar(100) not null,
    banco varchar(100),
    limite numeric(10, 2) not null default 0,
    vencimento integer,
    responsavel varchar(100),
    criado_por uuid references auth.users (id) on delete set null,
    criado_em timestamptz not null default now()
);

create table if not exists receitas (
    id uuid primary key default gen_random_uuid(),
    descricao varchar(150) not null,
    categoria varchar(60),
    valor numeric(10, 2) not null,
    data date not null,
    tipo varchar(20) not null default 'Variável' check (tipo in ('Fixa', 'Variável')),
    status varchar(20) not null default 'Recebido' check (status in ('Recebido', 'Previsto')),
    observacao text,
    criado_por uuid references auth.users (id) on delete set null,
    criado_em timestamptz not null default now()
);

create table if not exists despesas (
    id uuid primary key default gen_random_uuid(),
    descricao varchar(150) not null,
    categoria varchar(60),
    valor numeric(10, 2) not null,
    data date not null,
    pagamento varchar(30),
    tipo varchar(20) not null default 'Variável' check (tipo in ('Fixa', 'Variável')),
    status varchar(20) not null default 'Pago' check (status in ('Pago', 'Previsto')),
    observacao text,
    criado_por uuid references auth.users (id) on delete set null,
    criado_em timestamptz not null default now()
);

create table if not exists parcelamentos (
    id uuid primary key default gen_random_uuid(),
    descricao varchar(150) not null,
    valor_total numeric(10, 2) not null,
    parcelas integer not null check (parcelas >= 1),
    pagas integer not null default 0,
    data date not null,
    cartao varchar(100),
    criado_por uuid references auth.users (id) on delete set null,
    criado_em timestamptz not null default now()
);

-- Notas e tarefas do calendário (também usado para lembretes de contas)
create table if not exists notas_tarefas (
    id uuid primary key default gen_random_uuid(),
    titulo varchar(150) not null,
    descricao text,
    data date not null,
    tipo varchar(20) not null default 'Tarefa' check (tipo in ('Tarefa', 'Nota')),
    concluida boolean not null default false,
    criado_por uuid references auth.users (id) on delete set null,
    criado_em timestamptz not null default now()
);

-- ---------------------------------------------------------------------------
-- Row Level Security — qualquer usuário autenticado (toda a família) tem
-- acesso total aos dados da casa; usuários anônimos não acessam nada.
-- ---------------------------------------------------------------------------
alter table perfis enable row level security;
alter table membros_familia enable row level security;
alter table cartoes enable row level security;
alter table receitas enable row level security;
alter table despesas enable row level security;
alter table parcelamentos enable row level security;
alter table notas_tarefas enable row level security;

drop policy if exists "perfis: leitura autenticada" on perfis;
create policy "perfis: leitura autenticada" on perfis
    for select using (auth.role() = 'authenticated');

drop policy if exists "perfis: cada um atualiza o próprio" on perfis;
create policy "perfis: cada um atualiza o próprio" on perfis
    for update using (auth.uid() = id);

do $$
declare
    tabela text;
begin
    foreach tabela in array array['membros_familia', 'cartoes', 'receitas', 'despesas', 'parcelamentos', 'notas_tarefas']
    loop
        execute format('drop policy if exists "%s: acesso autenticado" on %I;', tabela, tabela);
        execute format(
            'create policy "%s: acesso autenticado" on %I for all using (auth.role() = ''authenticated'') with check (auth.role() = ''authenticated'');',
            tabela, tabela
        );
    end loop;
end $$;
