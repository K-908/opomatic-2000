# Entorno de PHP/MySQL y requisitos del hosting

## Verificado localmente (11 de octubre de 2026)

| Componente | Resultado |
|---|---|
| PHP CLI | 8.2.12; funciones `password_hash()` y `random_bytes()` disponibles |
| Extensiones PHP | `pdo_mysql`, `mbstring`, `openssl`, `json`, `session`, `fileinfo` cargadas |
| Composer | 2.10.2, instalado localmente |
| Apache | 2.4.58; módulos `rewrite`, `ssl` y `php` cargados |
| MySQL | Servicio MySQL80 activo; servidor 8.0.46 accesible desde PDO |
| Esquema | `database/schema.sql` creó 9 tablas, 10 claves foráneas y 10 `CHECK` en base temporal |
| Integridad | PDO confirmó inserción relacionada, rechazo de FK y CHECK, y rollback |
| Límites PHP CLI | `post_max_size=40M`, `memory_limit=512M`, `max_execution_time=0` |
| Sesiones PHP CLI | `session.gc_maxlifetime=1440` segundos; deberá configurarse para la sesión prevista de 12 horas o usar otro almacén |

La base temporal se eliminó tras la prueba. No se escribieron contraseñas en
este archivo. Las versiones locales no prueban que un futuro hosting tenga las
mismas capacidades.
Los límites de PHP CLI tampoco garantizan los valores de Apache o del hosting.

## Capacidades que necesita el hosting

1. PHP **8.2 o superior** para reproducir el entorno local, con `pdo_mysql`,
   `mbstring`, `openssl`, `json` y sesiones. Composer puede ejecutarse en local
   si el proveedor permite subir sus dependencias; Node.js no es necesario en
   el servidor porque React se publica compilado.
2. MySQL **8.0.16 o superior** con InnoDB, `utf8mb4`, claves foráneas y `CHECK`
   aplicados, o una versión de MariaDB en la que se haya probado el esquema
   completo. Permisos para crear tablas, índices y claves desde phpMyAdmin o
   una herramienta equivalente. La base puede tener un nombre prefijado por el
   proveedor: se configurará por variable, sin fijarlo en el código.
3. Apache con reescritura de URL o mecanismo equivalente: `/api/*` y `/health`
   deben llegar a PHP, y las rutas de React deben servir `index.html` al
   recargar. Hay que confirmar si se permite `.htaccess` y cómo se define el
   directorio público.
4. HTTPS válido para usar cookies `Secure`, y un lugar fuera del directorio
   público para configuración, código PHP privado, dependencias y copias. La
   cuenta del servidor debe poder usar almacenamiento de sesiones o una opción
   equivalente que soporte la duración prevista.
5. Un límite de peticiones suficiente para el JSON de importación de hasta
   5 MB. React envía JSON en el cuerpo de `POST`, así que debe comprobarse
   `post_max_size` y cualquier límite HTTP del proveedor; `upload_max_filesize`
   no controla esta ruta. También se probará el tiempo de procesamiento de
   2.000 preguntas en ese servidor.

## Datos pendientes del proveedor

No se ha elegido uno todavía. Al seleccionarlo, registrar aquí:

- Nombre del proveedor y plan; versiones PHP y MySQL/MariaDB disponibles.
- Extensiones PHP activas, límites de POST, memoria y tiempo de ejecución.
- Directorio público real y posibilidad de mantener archivos privados fuera.
- Reglas de reescritura admitidas, HTTPS y gestión de certificados.
- Resultado de importar `database/schema.sql` en una base vacía y de probar una
  inserción con restricciones y rollback.

La elección del hosting no impide empezar el backend local. La verificación de
publicación se cerrará antes de P13/P14.
