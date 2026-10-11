# Auditoría del MVP de OpoMatic-2000

## Estado

Pendiente. El MVP de OpoMatic-3000 fue auditado en su repositorio, pero esa
evidencia no demuestra que el port PHP cumpla los mismos requisitos. El
frontend está copiado; PHP responde a `GET /health` y tiene infraestructura
HTTP, pero no hay endpoints funcionales.

## Comprobaciones para el cierre

| Área | Evidencia necesaria en OpoMatic-2000 | Estado |
|---|---|---|
| Contrato HTTP | Rutas, estados, JSON y Problem Details iguales a `diseno-api.txt` | Pendiente |
| MySQL | Esquema, claves y transacciones probadas en la versión del hosting | Pendiente |
| Cuentas | Hashes PHP, sesiones, CSRF, límite de login y separación de datos | Pendiente |
| Tests | Selección, token, puntuación, idempotencia y snapshots | Pendiente |
| Frontend | Lint, pruebas, build y recorrido E2E contra PHP | Pendiente |
| Hosting | HTTPS, rutas Apache, secretos privados, copias y restauración | Pendiente |

El esquema inicial se importó y admitió una inserción relacionada con rollback
en MySQL 8.0.46 local. Esto valida el SQL inicial, no la futura aplicación PHP.

Consulta `requisitos.txt` para historias y reglas de negocio. Al cerrar el
port, registra aquí resultados reales y versiones del entorno comprobado.
