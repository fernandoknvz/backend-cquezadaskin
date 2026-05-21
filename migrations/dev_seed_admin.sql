-- CQuezadaSkin - Local development admin seed
-- Use only in local/dev. It creates or updates a known superadmin account.
--
-- email: admin@cquezadaskin.local
-- username: admin
-- password: Admin123456*

SET @dev_admin_hash := '$2y$10$cXICz7onwRY8UInOgOH.te2ucd96WxHREtGfSHbc/4BxzeIbe/s5S';

UPDATE usuarios_admin
SET
    email = 'admin@cquezadaskin.local',
    password_hash = @dev_admin_hash,
    rol = 'superadmin'
WHERE username = 'admin';

INSERT INTO usuarios_admin (username, email, password_hash, rol)
SELECT 'admin', 'admin@cquezadaskin.local', @dev_admin_hash, 'superadmin'
WHERE NOT EXISTS (
    SELECT 1
    FROM usuarios_admin
    WHERE username = 'admin'
       OR email = 'admin@cquezadaskin.local'
);
