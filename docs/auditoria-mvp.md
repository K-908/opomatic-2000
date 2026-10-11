# Auditoría del MVP de OpoMatic-2000

## Estado

Pendiente. El MVP de OpoMatic-3000 fue auditado en su repositorio, pero esa
evidencia no demuestra que el port PHP cumpla los mismos requisitos. El
frontend está copiado; PHP responde a `GET /health` y tiene infraestructura
HTTP y acceso MySQL, pero no hay endpoints funcionales.

## Comprobaciones para el cierre

| Área | Evidencia necesaria en OpoMatic-2000 | Estado |
|---|---|---|
| Contrato HTTP | Rutas, estados, JSON y Problem Details iguales a `diseno-api.txt` | Pendiente |
| MySQL | Esquema, claves y transacciones probadas en la versión del hosting | Pendiente |
| Cuentas | Hashes PHP, sesiones, CSRF, límite de login y separación de datos | Autenticación y accesos probados localmente; otras rutas pendientes |
| Tests | Selección, token, puntuación, idempotencia y snapshots | Pendiente |
| Frontend | Lint, pruebas, build y recorrido E2E contra PHP | Pendiente |
| Hosting | HTTPS, rutas Apache, secretos privados, copias y restauración | Pendiente |

En MySQL 8.0.46 local, la prueba P03 importó el esquema, verificó nueve tablas,
10 FK y 10 CHECK, probó repositorios, consultas preparadas, commit y rollback,
y restauró una copia en otra base temporal. Esto valida la infraestructura de
acceso, no los endpoints PHP ni el hosting definitivo.

P04 verificó en otra base temporal la creación única de `Gogloglo` y
`Virginia`, hashes comprobados con `password_verify()`, rechazo de repetición y
rollback ante un fallo en la segunda inserción. P05 verificó por HTTP los dos
logins, cookies, CSRF, expiración, logout, límite por IP y registros de acceso
aislados. Aún falta verificar aislamiento de intentos y estadísticas cuando
existan esas rutas y comprobar HTTPS/cookies en el hosting real.

Consulta `requisitos.txt` para historias y reglas de negocio. Al cerrar el
port, registra aquí resultados reales y versiones del entorno comprobado.
