-- Casa Organizada — schema MySQL/MariaDB (uso local com WampServer)
-- Importe pelo phpMyAdmin (aba "Importar") ou cole na aba "SQL".
-- Pode rodar quantas vezes quiser: todos os comandos são idempotentes.
--
-- Mesmo modelo do database/supabase.sql: "casa compartilhada" — qualquer
-- usuário logado (família toda) lê e escreve os mesmos registros. Os IDs são
-- UUID (CHAR(36)) gerados pela API em PHP, iguais aos do Supabase.

CREATE DATABASE IF NOT EXISTS casa_organizada
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE casa_organizada;

-- Usuários do app (equivalente ao auth.users + perfis do Supabase)
CREATE TABLE IF NOT EXISTS usuarios (
    id CHAR(36) PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    acesso_invest TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- acesso_invest: libera o Projeto invest (porta 8081) para a conta.
-- Contas novas nascem sem acesso; libere pelo phpMyAdmin ou com:
--   UPDATE usuarios SET acesso_invest = 1 WHERE email = 'seu@email.com';
-- (bloco abaixo adiciona a coluna em bancos criados antes dela existir)
SET @tem_coluna = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'acesso_invest');
SET @sql = IF(@tem_coluna = 0,
    'ALTER TABLE usuarios ADD COLUMN acesso_invest TINYINT(1) NOT NULL DEFAULT 0 AFTER senha',
    'SELECT 1');
PREPARE passo FROM @sql;
EXECUTE passo;
DEALLOCATE PREPARE passo;

-- Tentativas de login com senha errada e contas criadas (limite contra força bruta)
CREATE TABLE IF NOT EXISTS tentativas_login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(255) NOT NULL,
    momento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (chave, momento)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS membros_familia (
    id CHAR(36) PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    parentesco VARCHAR(50),
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cartoes (
    id CHAR(36) PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    banco VARCHAR(100),
    limite DECIMAL(10, 2) NOT NULL DEFAULT 0,
    vencimento INT,
    responsavel VARCHAR(100),
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS receitas (
    id CHAR(36) PRIMARY KEY,
    descricao VARCHAR(150) NOT NULL,
    categoria VARCHAR(60),
    valor DECIMAL(10, 2) NOT NULL,
    data DATE NOT NULL,
    tipo ENUM('Fixa', 'Variável') NOT NULL DEFAULT 'Variável',
    status ENUM('Recebido', 'Previsto') NOT NULL DEFAULT 'Recebido',
    observacao TEXT,
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS despesas (
    id CHAR(36) PRIMARY KEY,
    descricao VARCHAR(150) NOT NULL,
    categoria VARCHAR(60),
    valor DECIMAL(10, 2) NOT NULL,
    data DATE NOT NULL,
    pagamento VARCHAR(30),
    tipo ENUM('Fixa', 'Variável') NOT NULL DEFAULT 'Variável',
    status ENUM('Pago', 'Previsto') NOT NULL DEFAULT 'Pago',
    observacao TEXT,
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parcelamentos (
    id CHAR(36) PRIMARY KEY,
    descricao VARCHAR(150) NOT NULL,
    valor_total DECIMAL(10, 2) NOT NULL,
    parcelas INT NOT NULL,
    pagas INT NOT NULL DEFAULT 0,
    data DATE NOT NULL,
    cartao VARCHAR(100),
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (parcelas >= 1),
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Notas e tarefas do calendário (também usado para lembretes de contas)
CREATE TABLE IF NOT EXISTS notas_tarefas (
    id CHAR(36) PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT,
    data DATE NOT NULL,
    tipo ENUM('Tarefa', 'Nota') NOT NULL DEFAULT 'Tarefa',
    concluida TINYINT(1) NOT NULL DEFAULT 0,
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Objetivos e metas da família (casa, carro, viagens...): quanto já foi
-- guardado (valor_atual), quanto se quer juntar (valor_meta) e até quando.
CREATE TABLE IF NOT EXISTS objetivos (
    id CHAR(36) PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    categoria ENUM('Casa', 'Carro', 'Viagem', 'Passeio', 'Reserva', 'Outro') NOT NULL DEFAULT 'Outro',
    valor_meta DECIMAL(12, 2) NULL,
    valor_atual DECIMAL(12, 2) NOT NULL DEFAULT 0,
    prazo DATE NULL,
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Objetivos iniciais, cada um com R$ 250,00 já guardados (só cria se não existir)
INSERT INTO objetivos (id, nome, categoria, valor_atual)
SELECT UUID(), 'Comprar casa', 'Casa', 250 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM objetivos WHERE nome = 'Comprar casa');
INSERT INTO objetivos (id, nome, categoria, valor_atual)
SELECT UUID(), 'Comprar carro', 'Carro', 250 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM objetivos WHERE nome = 'Comprar carro');
INSERT INTO objetivos (id, nome, categoria, valor_atual)
SELECT UUID(), 'Viagens', 'Viagem', 250 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM objetivos WHERE nome = 'Viagens');
INSERT INTO objetivos (id, nome, categoria, valor_atual)
SELECT UUID(), 'Passeios', 'Passeio', 250 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM objetivos WHERE nome = 'Passeios');

-- Lançamentos de cada objetivo: dinheiro guardado ou investimento feito pelo
-- Casa no Projeto invest (ticker/quantidade/preço = compra registrada lá).
CREATE TABLE IF NOT EXISTS objetivo_aportes (
    id CHAR(36) PRIMARY KEY,
    objetivo_id CHAR(36) NOT NULL,
    tipo ENUM('Dinheiro', 'Investimento') NOT NULL DEFAULT 'Dinheiro',
    data DATE NOT NULL,
    valor DECIMAL(12, 2) NOT NULL,
    descricao VARCHAR(150) NULL,
    ticker VARCHAR(12) NULL,
    quantidade DECIMAL(14, 4) NULL,
    preco DECIMAL(12, 4) NULL,
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (objetivo_id, data),
    FOREIGN KEY (objetivo_id) REFERENCES objetivos (id) ON DELETE CASCADE,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Ativos do Projeto invest vinculados a um objetivo (cada ativo em um objetivo só,
-- para o mesmo dinheiro não contar duas vezes). O valor vem da posição atual no invest.
CREATE TABLE IF NOT EXISTS objetivo_ativos (
    ticker VARCHAR(12) PRIMARY KEY,
    objetivo_id CHAR(36) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (objetivo_id) REFERENCES objetivos (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Histórico: os R$ 250,00 iniciais de cada objetivo padrão, investidos por Matheus
-- (só se ainda não houver lançamentos)
INSERT INTO objetivo_aportes (id, objetivo_id, tipo, data, valor, descricao, criado_por)
SELECT UUID(), o.id, 'Dinheiro', DATE(o.criado_em), 250, 'Valor inicial já investido',
       (SELECT id FROM usuarios WHERE email = 'seu@email.com')
FROM objetivos o
WHERE o.nome IN ('Comprar casa', 'Comprar carro', 'Viagens', 'Passeios')
  AND NOT EXISTS (SELECT 1 FROM objetivo_aportes a WHERE a.objetivo_id = o.id);

-- admin: pode cadastrar, editar e excluir as contas de acesso (página Família).
-- (adiciona a coluna em bancos criados antes dela existir; Matheus começa como admin)
SET @tem_coluna = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'admin');
SET @sql = IF(@tem_coluna = 0,
    'ALTER TABLE usuarios ADD COLUMN admin TINYINT(1) NOT NULL DEFAULT 0 AFTER acesso_invest',
    'SELECT 1');
PREPARE passo FROM @sql;
EXECUTE passo;
DEALLOCATE PREPARE passo;
UPDATE usuarios SET admin = 1
WHERE email = 'seu@email.com' AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM usuarios WHERE admin = 1) x);

-- Categoria "Reserva" (reserva de emergência) para bancos criados antes dela
ALTER TABLE objetivos MODIFY COLUMN categoria ENUM('Casa', 'Carro', 'Viagem', 'Passeio', 'Reserva', 'Outro') NOT NULL DEFAULT 'Outro';

-- Metas de gastos (limites diários, mensais e anuais) comparadas com as despesas lançadas.
-- categoria NULL = todas as despesas; senão, só as despesas daquela categoria.
CREATE TABLE IF NOT EXISTS metas_gastos (
    id CHAR(36) PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    periodo ENUM('Diária', 'Mensal', 'Anual') NOT NULL,
    categoria VARCHAR(60) NULL,
    nota TEXT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Histórico de limites de cada meta: mudar o limite cria uma nova versão (com nota e autor);
-- cada período é comparado com o limite que valia nele (a versão mais recente até o fim do período).
CREATE TABLE IF NOT EXISTS meta_gasto_versoes (
    id CHAR(36) PRIMARY KEY,
    meta_id CHAR(36) NOT NULL,
    valor_limite DECIMAL(12, 2) NOT NULL,
    vigente_desde DATE NOT NULL,
    nota VARCHAR(500) NULL,
    criado_por CHAR(36) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (meta_id, vigente_desde),
    FOREIGN KEY (meta_id) REFERENCES metas_gastos (id) ON DELETE CASCADE,
    FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Despesas: quem gastou (pessoa da família ou conta). Vazio = quem lançou a despesa.
SET @tem_coluna = (SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'despesas' AND column_name = 'gasto_por');
SET @sql = IF(@tem_coluna = 0, 'ALTER TABLE despesas ADD COLUMN gasto_por VARCHAR(100) NULL AFTER pagamento', 'SELECT 1');
PREPARE passo FROM @sql;
EXECUTE passo;
DEALLOCATE PREPARE passo;
