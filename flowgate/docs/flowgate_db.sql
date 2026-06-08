-- flowgate_db.sql
-- ============================================================
--  Banco de Dados: flowgate_db
--  Compatível com: MariaDB 10.5+
--
--  A Flowgate agrega múltiplas fornecedoras em um único ponto
--  de acesso. A Automax (e qualquer outro cliente) consulta
--  este banco para descobrir disponibilidade e preços de peças
--  sem precisar falar com cada fornecedora individualmente.
--
--  Relação com oficina_db:
--    - flowgate_db.pecas → referenciada por oficina_db.pecas
--      via id_peca_flowgate (coluna adicionada no schema da Automax)
--    - A Automax consome a API da Flowgate; os bancos NÃO
--      compartilham servidor de aplicação.
-- ============================================================

CREATE DATABASE IF NOT EXISTS flowgate_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE flowgate_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. fornecedoras
--    Cada linha é uma empresa parceira da Flowgate.
-- ============================================================
CREATE TABLE IF NOT EXISTS fornecedoras (
    id_fornecedora   INT          NOT NULL AUTO_INCREMENT,
    nome             VARCHAR(255) NOT NULL,
    cnpj             VARCHAR(18)  NOT NULL,
    email_contato    VARCHAR(255) NOT NULL,
    telefone         VARCHAR(20),
    ativa            TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_fornecedora),
    UNIQUE KEY uq_cnpj (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. categorias
--    Taxonomia de peças — evita strings livres espalhadas.
-- ============================================================
CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INT          NOT NULL AUTO_INCREMENT,
    slug         VARCHAR(60)  NOT NULL,   -- ex: "filtros", "fluidos"
    nome         VARCHAR(100) NOT NULL,
    PRIMARY KEY (id_categoria),
    UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. pecas
--    Catálogo centralizado. Cada peça pertence a uma categoria
--    e é fornecida por uma fornecedora.
-- ============================================================
CREATE TABLE IF NOT EXISTS pecas (
    id_peca        INT            NOT NULL AUTO_INCREMENT,
    id_fornecedora INT            NOT NULL,
    id_categoria   INT            NOT NULL,
    nome           VARCHAR(255)   NOT NULL,
    codigo_sku     VARCHAR(100)   NOT NULL,  -- código interno da fornecedora
    descricao      TEXT,
    preco          DECIMAL(10,2)  NOT NULL,
    estoque        INT            NOT NULL DEFAULT 0,
    unidade        VARCHAR(20)    NOT NULL DEFAULT 'un',  -- un, litro, kg…
    ativo          TINYINT(1)     NOT NULL DEFAULT 1,
    atualizado_em  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_peca),
    UNIQUE KEY uq_sku_por_fornecedora (id_fornecedora, codigo_sku),
    CONSTRAINT fk_peca_fornecedora
        FOREIGN KEY (id_fornecedora) REFERENCES fornecedoras (id_fornecedora)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_peca_categoria
        FOREIGN KEY (id_categoria)   REFERENCES categorias   (id_categoria)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. api_keys
--    Clientes da Flowgate (ex: Automax) se autenticam com uma
--    chave de API em vez de usuário/senha.
-- ============================================================
CREATE TABLE IF NOT EXISTS api_keys (
    id_key       INT          NOT NULL AUTO_INCREMENT,
    cliente      VARCHAR(100) NOT NULL,   -- nome do sistema cliente
    chave        CHAR(64)     NOT NULL,   -- SHA-256 hex da chave real
    ativa        TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_key),
    UNIQUE KEY uq_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. consultas_log
--    Audit trail de todas as buscas feitas pelos clientes.
-- ============================================================
CREATE TABLE IF NOT EXISTS consultas_log (
    id_log       INT          NOT NULL AUTO_INCREMENT,
    id_key       INT,
    endpoint     VARCHAR(100) NOT NULL,
    parametros   TEXT,
    ip           VARCHAR(45),
    momento      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_log),
    CONSTRAINT fk_log_key
        FOREIGN KEY (id_key) REFERENCES api_keys (id_key)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED — dados iniciais para desenvolvimento
-- ============================================================

INSERT INTO categorias (slug, nome) VALUES
    ('filtros',    'Filtros'),
    ('fluidos',    'Fluidos e Óleos'),
    ('freios',     'Freios'),
    ('suspensao',  'Suspensão'),
    ('eletrico',   'Sistema Elétrico'),
    ('motor',      'Motor'),
    ('correia',    'Correias e Tensores'),
    ('outros',     'Outros');

INSERT INTO fornecedoras (nome, cnpj, email_contato, telefone) VALUES
    ('AutoPeças Brasil Ltda',   '12.345.678/0001-90', 'contato@autopecasbrasil.com.br', '(11) 3000-1111'),
    ('Distribuidora RapidPart', '98.765.432/0001-10', 'vendas@rapidpart.com.br',        '(41) 3000-2222'),
    ('MotoSupply SC',           '55.123.456/0001-33', 'sc@motosupply.com.br',           '(47) 3000-3333');

-- Chave de API da Automax (valor real: "automax-dev-key-2026" — SHA-256 abaixo)
-- Em produção: gerar com bin2hex(random_bytes(32)) e armazenar o hash
INSERT INTO api_keys (cliente, chave) VALUES
    ('Automax', sha2('automax-dev-key-2026', 256));

INSERT INTO pecas (id_fornecedora, id_categoria, nome, codigo_sku, descricao, preco, estoque, unidade) VALUES
    (1, 1, 'Filtro de Óleo Bosch F026407006',   'FO-B-7006',  'Filtro de óleo compatível com motores 1.0 a 2.0 flex.',        42.90,  80, 'un'),
    (1, 1, 'Filtro de Ar K&N 33-2031',          'FA-KN-2031', 'Filtro de ar esportivo lavável, alta vazão.',                  189.00,  30, 'un'),
    (1, 1, 'Filtro de Combustível Tecfil ARL21', 'FC-TC-21',   'Para veículos flex 2012 em diante.',                           35.50,  60, 'un'),
    (2, 2, 'Óleo Mobil 1 5W-30 Sintético 1L',   'OL-MB-5W30', 'Óleo de motor sintético API SN/CF.',                          69.90, 120, 'litro'),
    (2, 2, 'Fluido de Freio Castrol DOT 4 500ml','FF-CS-D4',   'Ponto de ebulição seco 265 °C.',                               28.00, 100, 'un'),
    (2, 2, 'Aditivo Radiador Prestone 1L',       'AR-PR-1L',   'Concentrado, protege contra corrosão e cavitação.',            39.90,  90, 'litro'),
    (3, 3, 'Pastilha de Freio Dianteira Fras-le','PF-FL-D01',  'Eixo dianteiro, compatível com HB20, Onix, Polo 2018+.',      98.00,  50, 'jogo'),
    (3, 3, 'Disco de Freio Ventilado Brembo',    'DF-BR-V12',  'Par dianteiro, 280mm, ventilado.',                            245.00,  20, 'par'),
    (1, 4, 'Amortecedor Dianteiro Monroe',       'AM-MN-D55',  'Gas-Magnum, eixo dianteiro universal.',                       210.00,  25, 'un'),
    (2, 5, 'Bateria Moura 60Ah MF60GE',          'BT-MR-60',   'Bateria selada, 60Ah, 18 meses de garantia.',                390.00,  15, 'un'),
    (3, 6, 'Jogo de Velas NGK BKR5E',            'VL-NGK-5E',  'Conjunto com 4 velas, eletrodo de cobre.',                    72.00,  70, 'jogo'),
    (1, 7, 'Correia Dentada Gates 5476XS',       'CD-GT-5476', 'Kit correia + tensor, motores 1.6 16v.',                     145.00,  35, 'kit');

-- ============================================================
-- FIM DO SCRIPT
-- ============================================================
