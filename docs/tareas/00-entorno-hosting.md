# P00. Entorno local y requisitos del hosting

Estado: **[x] Completado**. La comprobación local y del servidor elegido está
terminada. Sus parámetros específicos se conservan solo en local.

## Trabajo local realizado — 2026-10-11

- PHP 8.2.12 CLI y Composer 2.10.2 disponibles.
- Extensiones `pdo_mysql`, `mbstring`, `openssl`, `json`, `session` y `fileinfo`
  disponibles; `password_hash()` y `random_bytes()` funcionan como funciones.
- Apache local 2.4.58 con módulos `rewrite`, `ssl` y `php` cargados.
- Servicio MySQL80 activo, MySQL 8.0.46. PHP conecta por PDO a `127.0.0.1:3306`.
- PHP CLI admite `post_max_size=40M`; su `session.gc_maxlifetime` actual es
  1.440 segundos, inferior a las doce horas deseadas y debe configurarse en
  el backend; P05 lo ajusta a 12 horas y usa un directorio privado, pendiente
  de verificar en el hosting elegido.
- `database/schema.sql` importado en base aislada: nueve tablas, diez claves
  foráneas y diez restricciones `CHECK` registradas.
- PDO insertó filas relacionadas, rechazó una clave foránea inválida y un
  nombre que incumplía un `CHECK`; el rollback dejó la tabla vacía.
- La base temporal fue eliminada después de verificarla. No se tocaron las
  bases existentes ni se guardaron credenciales en el repositorio.

## Comprobación del servidor elegido — 2026-10-12

Se comprobaron la versión de PHP y de MariaDB, las extensiones necesarias,
la conexión PDO, la importación del esquema en una base temporal y las
restricciones de integridad. También se verificaron HTTPS, enrutamiento de API
y SPA, separación de archivos públicos y privados, login y restauración de
una copia SQL en una segunda base temporal.

La configuración y los identificadores del servidor se documentan en un
archivo local ignorado por Git.
