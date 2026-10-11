# P00. Entorno local y requisitos del hosting

Estado: **[~] En curso**. La comprobación local está terminada; falta verificar
el proveedor concreto cuando se elija.

## Trabajo local realizado — 2026-10-11

- PHP 8.2.12 CLI y Composer 2.10.2 disponibles.
- Extensiones `pdo_mysql`, `mbstring`, `openssl`, `json`, `session` y `fileinfo`
  disponibles; `password_hash()` y `random_bytes()` funcionan como funciones.
- Apache local 2.4.58 con módulos `rewrite`, `ssl` y `php` cargados.
- Servicio MySQL80 activo, MySQL 8.0.46. PHP conecta por PDO a `127.0.0.1:3306`.
- PHP CLI admite `post_max_size=40M`; su `session.gc_maxlifetime` actual es
  1.440 segundos, inferior a las doce horas deseadas y debe configurarse en
  el futuro backend o sustituirse por otro almacén de sesión.
- `database/schema.sql` importado en base aislada: nueve tablas, diez claves
  foráneas y diez restricciones `CHECK` registradas.
- PDO insertó filas relacionadas, rechazó una clave foránea inválida y un
  nombre que incumplía un `CHECK`; el rollback dejó la tabla vacía.
- La base temporal fue eliminada después de verificarla. No se tocaron las
  bases existentes ni se guardaron credenciales en el repositorio.

## Requisitos para elegir hosting

Están detallados en [`../entorno-hosting.md`](../entorno-hosting.md). Hay que
confirmar la versión real de PHP y MySQL/MariaDB, las extensiones, permisos de
base de datos, rutas Apache, HTTPS, límites de petición y separación de archivos
públicos/privados. El servidor concreto sigue sin identificarse.

## Cierre pendiente

Cuando se elija hosting: registrar proveedor y versiones, probar importación
del esquema en una base vacía de ese proveedor, comprobar reescritura de rutas
y HTTPS, y actualizar este estado a terminado con evidencia. Esto no bloquea
P01–P12, que pueden desarrollarse y probarse localmente.
