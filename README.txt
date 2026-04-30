CQUEZADASKIN - BACKEND PHP

Backend PHP para agenda, reservas, servicios, disponibilidad, clientes y administracion.

Stack detectado:
- PHP con router unico en index.php.
- PDO MySQL.
- firebase/php-jwt para autenticacion JWT.
- PHPMailer para correos SMTP.

Configuracion:
1. Copiar .env.example a .env.
2. Mantener DB_NAME con el nombre real de la base existente si el hosting aun lo usa.
3. Configurar DB_USER, DB_PASS, JWT_SECRET y credenciales SMTP.
4. Configurar marca visible:
   APP_NAME=CQuezadaSkin
   BRAND_NAME=CQuezadaSkin
   MAIL_FROM_NAME="CQuezadaSkin"
   MAIL_REPLY_TO_NAME="CQuezadaSkin"
5. Si el frontend nuevo usa otros dominios, agregarlos en CORS_ALLOWED_ORIGINS separados por coma.

Rutas principales:
- GET /api
- POST /api/login
- POST /api/logout
- GET /api/me
- POST /api/register_admin
- GET|PUT /api/account
- POST /api/forgot-password
- POST /api/reset-password
- GET|POST|PUT|DELETE /api/categorias
- GET|POST|PUT|DELETE /api/servicios
- GET|POST|PUT|DELETE /api/citas
- POST /api/reservas
- GET /api/disponibilidad
- POST /api/clientes/register
- POST /api/clientes/login
- GET|PUT|PATCH|DELETE /api/clientes/me
- GET /api/clientes/reservas
- GET /api/dashboard/overview
- GET /api/dashboard/citas-hoy
- GET /api/dashboard/top-servicios

Notas de compatibilidad:
- No renombrar tablas ni columnas existentes.
- No cambiar paths de API consumidos por el frontend.
- No eliminar variables antiguas mientras existan ambientes productivos que las usen.
- Los nombres antiguos en bases, dumps, workflows o redes Docker pueden ser acoplamientos internos de despliegue.
