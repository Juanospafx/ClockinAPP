-- 2026-04-07_users_soft_delete_unique_active.sql
-- Objetivo: permitir reutilizar username de usuarios soft-deleted,
-- manteniendo unicidad solo para usuarios activos (deleted_at IS NULL).
-- Motor objetivo: MySQL / MariaDB.

-- 1) Garantiza columna de soft delete e índice auxiliar.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_users_deleted_at ON users (deleted_at);

-- 2) Remueve unicidad global antigua sobre username (bloquea reutilización tras soft delete).
ALTER TABLE users DROP INDEX username;

-- 3) Columna generada: solo replica username para usuarios activos.
--    En eliminados queda NULL, por lo que no participa en choque UNIQUE.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS username_active VARCHAR(50)
        GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN username ELSE NULL END) STORED;

-- 4) Unicidad efectiva solo para activos.
CREATE UNIQUE INDEX uq_users_username_active ON users (username_active);
