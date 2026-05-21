-- CQuezadaSkin - Admin auth email support
-- Safe to run more than once. Does not delete users or change password_hash.

SET @admin_email_column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios_admin'
      AND COLUMN_NAME = 'email'
);

SET @admin_email_column_sql := IF(
    @admin_email_column_exists = 0,
    'ALTER TABLE usuarios_admin ADD COLUMN email VARCHAR(190) NULL AFTER username',
    'SELECT "usuarios_admin.email already exists"'
);

PREPARE admin_email_column_stmt FROM @admin_email_column_sql;
EXECUTE admin_email_column_stmt;
DEALLOCATE PREPARE admin_email_column_stmt;

SET @admin_email_index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios_admin'
      AND INDEX_NAME = 'usuarios_admin_email_unique'
);

SET @admin_email_index_sql := IF(
    @admin_email_index_exists = 0,
    'ALTER TABLE usuarios_admin ADD UNIQUE KEY usuarios_admin_email_unique (email)',
    'SELECT "usuarios_admin_email_unique already exists"'
);

PREPARE admin_email_index_stmt FROM @admin_email_index_sql;
EXECUTE admin_email_index_stmt;
DEALLOCATE PREPARE admin_email_index_stmt;
