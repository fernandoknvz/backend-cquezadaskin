# Railway MySQL deploy

Este directorio contiene el schema limpio para preparar la base de datos de produccion del backend `backend-cquezadaskin` en Railway.

## Archivo principal

Usa:

```bash
database/production_schema.sql
```

Fue consolidado tomando `c2701519_cqs.sql` como base principal e incorporando las migraciones actuales. No incluye credenciales, usuarios admin de desarrollo, clientes, citas, contactos, refresh tokens ni tokens de recuperacion.

## Importar en Railway MySQL

Ejecutar desde una terminal con las variables del servicio MySQL disponibles:

```bash
mysql \
  --host="$MYSQLHOST" \
  --port="$MYSQLPORT" \
  --user="$MYSQLUSER" \
  --password="$MYSQLPASSWORD" \
  "$MYSQLDATABASE" < database/production_schema.sql
```

Si se ejecuta desde un entorno donde las variables usan los nombres mapeados del backend:

```bash
mysql \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USER" \
  --password="$DB_PASS" \
  "$DB_NAME" < database/production_schema.sql
```

Importar solo sobre una base vacia o recien creada. Este archivo no hace `CREATE DATABASE`, no hace `USE` y no borra tablas existentes.

## Variables a mapear al backend

En el servicio backend de Railway, mapear las variables desde el servicio MySQL asi:

```env
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_NAME=${{MySQL.MYSQLDATABASE}}
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASS=${{MySQL.MYSQLPASSWORD}}
```

## Admin inicial

El schema deja `usuarios_admin` vacio a proposito. Crear el usuario admin inicial con un hash bcrypt generado de forma segura para produccion, o mediante el flujo administrativo que corresponda. No reutilizar usuarios ni hashes de los dumps de desarrollo.

Ejemplo de insercion manual, reemplazando los placeholders:

```sql
INSERT INTO usuarios_admin (username, email, password_hash, rol)
VALUES ('admin-produccion', 'admin@example.com', '<bcrypt_generado_para_una_password_nueva>', 'superadmin');
```

## Datos iniciales

El schema incluye contenido publico minimo editable desde el panel:

- `about_content`
- `categorias_servicio`
- `home_content`
- `servicios`
- `site_config`
- `faq`
- `post_care_tip`

Quedan vacias por seguridad:

- `usuarios_admin`
- `clientes`
- `citas`
- `contacto`
- `horarios_disponibles`
- `refresh_tokens`
- `password_resets`
- `testimonio`
