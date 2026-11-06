-- ============================================================
--  MeuBolso - Schema do Banco de Dados
--  Compatível com MySQL 5.7+ / MariaDB 10.3+
--
--  Uso:  mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS meubolso
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE meubolso;

-- ------------------------------------------------------------
-- Usuários
--   chave_recuperacao_hash : hash da chave de recuperação de senha
--   rec_tentativas / rec_bloqueado_ate : proteção contra força bruta
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id                      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome                    VARCHAR(100)  NOT NULL,
    email                   VARCHAR(150)  NOT NULL,
    senha_hash              VARCHAR(255)  NOT NULL,
    chave_recuperacao_hash  VARCHAR(255)  NULL,
    rec_tentativas          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    rec_bloqueado_ate       DATETIME      NULL,
    onboarding_completo     TINYINT(1)    NOT NULL DEFAULT 0,
    criado_em               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Categorias (compartilhadas por todos os usuários)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome    VARCHAR(60)  NOT NULL,
    icone   VARCHAR(10)  NOT NULL DEFAULT '📦',
    cor     VARCHAR(7)   NOT NULL DEFAULT '#888780',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categorias (id, nome, icone, cor) VALUES
    (1, 'Alimentação', '🍽️', '#E24B4A'),
    (2, 'Transporte',  '🚌', '#BA7517'),
    (3, 'Moradia',     '🏠', '#185FA5'),
    (4, 'Saúde',       '💊', '#1D9E75'),
    (5, 'Educação',    '📚', '#534AB7'),
    (6, 'Lazer',       '🎮', '#D4537E'),
    (7, 'Mercado',     '🛒', '#3B6D11'),
    (8, 'Outros',      '📦', '#888780');

-- ------------------------------------------------------------
-- Orçamento padrão (recorrente, um por usuário)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orcamento_padrao (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED  NOT NULL,
    limite     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    renda      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario (usuario_id),
    FOREIGN KEY fk_op_usuario (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Orçamento de um mês específico (sobrescreve o padrão)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orcamentos (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED  NOT NULL,
    mes        TINYINT       NOT NULL COMMENT '1-12',
    ano        SMALLINT      NOT NULL,
    limite     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    renda      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_mes_ano (usuario_id, mes, ano),
    FOREIGN KEY fk_orc_usuario (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Despesas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS despesas (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id   INT UNSIGNED  NOT NULL,
    categoria_id INT UNSIGNED  NOT NULL,
    descricao    VARCHAR(200)  NOT NULL,
    valor        DECIMAL(10,2) NOT NULL,
    data         DATE          NOT NULL,
    criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_usuario_data (usuario_id, data),
    FOREIGN KEY fk_desp_usuario   (usuario_id)   REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY fk_desp_categoria (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Receitas avulsas (renda extra, não recorrente)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS receitas_avulsas (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED  NOT NULL,
    descricao  VARCHAR(200)  NOT NULL,
    valor      DECIMAL(10,2) NOT NULL,
    data       DATE          NOT NULL,
    criado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ra_usuario_data (usuario_id, data),
    FOREIGN KEY fk_ra_usuario (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
