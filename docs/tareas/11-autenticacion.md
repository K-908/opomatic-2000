# 11. Autenticación y usuarios

Estado: pendiente. Dependencia de todas las rutas privadas.

## T100 - Cuentas iniciales

- Crear `Gogloglo` y `Virginia` en MySQL mediante un proceso PHP explícito con
  `password_hash()`; no guardar contraseñas en SQL ni en Git.
- No aceptar registro público ni recuperación de contraseña en este MVP.

**Comprobación:** hashes verificables con `password_verify()` y ausencia de
credenciales de prueba en el repositorio.

## T101 - Sesión y accesos

- Implementar login, sesión, logout y consulta de accesos recientes.
- Usar cookies HttpOnly y Secure en HTTPS, protección CSRF, límite de intentos
  y errores genéricos. Registrar fecha UTC e IP en logins correctos.
- Impedir que una cuenta acceda a intentos o estadísticas de otra.

**Comprobación:** expiración de doce horas, cierre, CSRF, límite de login y
aislamiento de datos probados por HTTP.
