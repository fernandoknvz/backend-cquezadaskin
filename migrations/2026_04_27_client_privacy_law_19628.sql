-- Proteccion de datos cliente - Ley 19.628 Chile
-- Ejecutar una sola vez sobre la base actual.

ALTER TABLE clientes
  ADD COLUMN acepta_politica tinyint(1) NOT NULL DEFAULT 0 AFTER password_hash,
  ADD COLUMN fecha_aceptacion datetime NULL AFTER acepta_politica;

CREATE INDEX idx_clientes_acepta_politica ON clientes (acepta_politica);
