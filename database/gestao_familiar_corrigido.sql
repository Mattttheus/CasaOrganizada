CREATE DATABASE IF NOT EXISTS gestao_familiar
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestao_familiar;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    celular VARCHAR(20) NULL,
    senha VARCHAR(255) NOT NULL,
    role ENUM('admin','usuario') DEFAULT 'usuario',
    ativo TINYINT(1) DEFAULT 1,
    tentativas_login INT NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Usuário padrão: e-mail admin@casaorganizada.com / senha admin123 (altere após o primeiro acesso).
INSERT INTO usuarios (nome, email, senha, role, ativo)
SELECT 'Administrador', 'admin@casaorganizada.com',
       '$2y$10$TuPZmBiHeCdQa3Mvj0K7buMECyL91m1FTDeZyU2JUIjH7uV3AuJVK',
       'admin', 1
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'admin@casaorganizada.com'
);

-- Usuário: Matheus / e-mail matheusviniciuscaieiras@gmail.com / senha Matheus@2026 (altere após o primeiro acesso).
INSERT INTO usuarios (nome, email, celular, senha, role, ativo)
SELECT 'Matheus', 'matheusviniciuscaieiras@gmail.com', '11942726317',
       '$2y$10$.Bt1YzAUAIpi87XlNhH0puZGAib256pZFNGP0fxRzFz2VE2CK/eB.',
       'usuario', 1
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'matheusviniciuscaieiras@gmail.com'
);

CREATE TABLE IF NOT EXISTS membros_familia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    parentesco VARCHAR(50),
    usuario_id INT NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_membro_usuario FOREIGN KEY (usuario_id)
      REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('receita','despesa') NOT NULL,
    icone VARCHAR(50),
    ativo TINYINT(1) DEFAULT 1,
    UNIQUE KEY uq_categoria_tipo (nome,tipo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cartoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    banco VARCHAR(100),
    limite_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    dia_fechamento INT,
    dia_vencimento INT,
    membro_id INT NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cartao_membro FOREIGN KEY (membro_id)
      REFERENCES membros_familia(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS receitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(150) NOT NULL,
    categoria_id INT NULL,
    membro_id INT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_receita DATE NOT NULL,
    tipo ENUM('Fixa','Variavel') DEFAULT 'Variavel',
    observacao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receita_categoria FOREIGN KEY (categoria_id)
      REFERENCES categorias(id) ON DELETE SET NULL,
    CONSTRAINT fk_receita_membro FOREIGN KEY (membro_id)
      REFERENCES membros_familia(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(150) NOT NULL,
    categoria_id INT NULL,
    membro_id INT NULL,
    cartao_id INT NULL,
    tipo ENUM('Fixa','Variavel') DEFAULT 'Variavel',
    forma_pagamento ENUM('PIX','Dinheiro','Cartao de Credito','Cartao de Debito','Boleto') NOT NULL,
    valor_previsto DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_real DECIMAL(10,2) NOT NULL DEFAULT 0,
    data_prevista DATE NULL,
    data_pagamento DATE NULL,
    status ENUM('Previsto','Pago','Atrasado') DEFAULT 'Previsto',
    observacao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_despesa_categoria FOREIGN KEY (categoria_id)
      REFERENCES categorias(id) ON DELETE SET NULL,
    CONSTRAINT fk_despesa_membro FOREIGN KEY (membro_id)
      REFERENCES membros_familia(id) ON DELETE SET NULL,
    CONSTRAINT fk_despesa_cartao FOREIGN KEY (cartao_id)
      REFERENCES cartoes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parcelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    despesa_id INT NOT NULL,
    numero_parcela INT NOT NULL,
    total_parcelas INT NOT NULL,
    valor_parcela DECIMAL(10,2) NOT NULL,
    data_vencimento DATE,
    status ENUM('Pendente','Pago') DEFAULT 'Pendente',
    CONSTRAINT fk_parcela_despesa FOREIGN KEY (despesa_id)
      REFERENCES despesas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS anotacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    texto VARCHAR(500) NOT NULL,
    data_agendamento DATE NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS metas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT,
    valor_meta DECIMAL(10,2) NOT NULL,
    valor_atual DECIMAL(10,2) DEFAULT 0,
    data_inicio DATE,
    data_final DATE,
    status ENUM('Em andamento','Concluida','Cancelada') DEFAULT 'Em andamento',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orcamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    mes INT NOT NULL,
    ano INT NOT NULL,
    valor_limite DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_orcamento_categoria FOREIGN KEY (categoria_id)
      REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO categorias (nome,tipo) VALUES
('Salário','receita'),('Freelance','receita'),('Outros','receita'),
('Alimentação','despesa'),('Moradia','despesa'),('Transporte','despesa'),
('Saúde','despesa'),('Educação','despesa'),('Lazer','despesa'),
('Contas','despesa'),('Compras','despesa'),('Outros','despesa')
ON DUPLICATE KEY UPDATE nome=VALUES(nome);
