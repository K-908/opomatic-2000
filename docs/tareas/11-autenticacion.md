# 11. Autenticación y usuarios

Estado: T100 y la autenticación de T101 completadas y probadas localmente
(P04–P05). El aislamiento de intentos y estadísticas se verificará al crear
esas rutas (P11–P12). Dependencia de todas las rutas privadas.

## T100 - Cuentas iniciales

- Crear `Gogloglo` y `Virginia` en MySQL mediante un proceso PHP explícito con
  `password_hash()`; no guardar contraseñas en SQL ni en Git.
- No aceptar registro público ni recuperación de contraseña en este MVP.

**Comprobación realizada:** en MySQL 8.0.46 temporal, el comando creó exactamente
las dos cuentas y `password_verify()` comprobó cada hash. Rechazó una contraseña
vacía, una tabla `Users` no vacía y una segunda ejecución sin cambiar los
hashes. Una falla forzada en la segunda inserción revirtió la primera. Las
contraseñas de prueba se generaron al vuelo; no hay credenciales en Git.

El comando está en `../../backend/bin/bootstrap-users.php`, fuera del directorio
público. Recibe dos líneas de contraseña por entrada estándar; el wrapper
`BootstrapUsers.ps1` las pide sin mostrarlas en Windows. La ejecución con las
contraseñas definitivas se hará una sola vez tras importar el esquema en el
hosting. Si el proveedor no ofrece PHP CLI o cron, P13 deberá resolver el alta
sin exponer un instalador público permanente.

## T101 - Sesión y accesos

- Implementar login, sesión, logout y consulta de accesos recientes.
- Usar cookies HttpOnly y Secure en HTTPS, protección CSRF, límite de intentos
  y errores genéricos. Registrar fecha UTC e IP en logins correctos.
- Impedir que una cuenta acceda a intentos o estadísticas de otra.

**Comprobación realizada:** en `RunDatabaseTest.ps1 -Authentication`, dos
clientes HTTP iniciaron sesión con cookies independientes. Se verificaron
`GET /api/auth/session`, logout, rechazo de CSRF ausente o erróneo, caducidad
absoluta a las doce horas, 429 en el sexto intento por IP/minuto y
`Retry-After`. Solo los logins correctos quedaron registrados con UTC e IP
del servidor. Cada cuenta vio únicamente sus accesos y el listado no superó
50 entradas. Las rutas de intentos y estadísticas todavía no existen.

La cookie de sesión es HttpOnly y SameSite=Lax; se marca Secure en HTTPS y en
producción. El token CSRF se entrega en otra cookie para que React lo envíe en
`X-CSRF-Token` en peticiones que cambian estado. Las sesiones y el límite de
login se guardan en `AUTH_STORAGE_DIR` (por defecto `backend/var/auth`), que
debe ser escribible y estar fuera del directorio público. PHP debe recibir la
marca HTTPS real del servidor; no se confía en cabeceras de proxy arbitrarias.
