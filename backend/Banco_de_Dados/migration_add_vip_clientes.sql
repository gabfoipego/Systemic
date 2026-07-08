-- Migration: adiciona a coluna vip na tabela clientes.
-- Rode este script apenas se o banco já existir (docker compose up sem -v).
-- Para bancos novos, a coluna já nasce criada via oficina_db_mariadb.sql.

ALTER TABLE clientes
    ADD COLUMN vip TINYINT(1) NOT NULL DEFAULT 0 AFTER email;