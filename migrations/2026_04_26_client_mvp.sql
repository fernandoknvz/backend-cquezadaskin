-- MVP clientes autenticados + reservas solicitadas
-- Ejecutar una sola vez sobre la base actual.

ALTER TABLE clientes
  ADD COLUMN password_hash varchar(255) NULL AFTER telefono;

ALTER TABLE clientes
  ADD UNIQUE KEY uniq_clientes_correo (correo);

ALTER TABLE citas
  MODIFY estado enum('solicitada','pendiente','confirmada','cancelada') DEFAULT 'pendiente';
