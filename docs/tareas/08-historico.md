# 08. Histórico y revisión

Estado: completado localmente (P11, 2/2). Listado, paginación y revisión
inmutable por cuenta verificados por HTTP y Chromium.

## [x] T070 - Consultas por cuenta

- Implementar lista paginada descendente y detalle de intento desde snapshots.
- Aislar datos por usuario autenticado; devolver 404 para intento ajeno.

**Comprobación:** edición o desactivación posterior de una pregunta no altera
la revisión histórica; una cuenta no ve intentos de la otra.

**Cierre — 2026-10-11**

- Implementado: Listado paginado por cuenta, orden estable fecha/ID y snapshots inmutables; detalle ajeno devuelve 404.
- Verificación: `RunDatabaseTest.ps1 -Progress` — OK (HTTP, Chromium y restauración); `npm.cmd run lint`, `npm.cmd run test:run` (54 pruebas) y `npm.cmd run build` — OK.
- Documentación: contrato HTTP/OpenAPI, README, arquitectura, persistencia, planes e índice actualizados; evidencia en `../auditoria-mvp.md`.

## [x] T071 - Integración React

- Verificar lista, paginación, detalle, vacíos y errores.

**Comprobación:** histórico navegable con las respuestas correctas y elegidas.

**Cierre — 2026-10-11**

- Implementado: Recorrido React de lista, paginación, recarga, detalle, vacíos, loading, errores, teclado y móvil.
- Verificación: `RunDatabaseTest.ps1 -Progress` — OK (HTTP, Chromium y restauración); `npm.cmd run lint`, `npm.cmd run test:run` (54 pruebas) y `npm.cmd run build` — OK.
- Documentación: contrato HTTP/OpenAPI, README, arquitectura, persistencia, planes e índice actualizados; evidencia en `../auditoria-mvp.md`.
