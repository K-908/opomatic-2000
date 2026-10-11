# 09. Estadísticas

Estado: completado localmente (P12, 2/2).

## [x] T080 - Agregaciones MySQL

- Calcular resumen global, actividad y ranking por tema desde snapshots.
- Excluir blancos del denominador de precisión y evitar división por cero.
- Mantener temas inactivos con histórico.

**Comprobación:** conjuntos vacíos, empates, totales y aislamiento por cuenta.

**Cierre — 2026-10-11**

- Implementado: Agregados SQL por cuenta, precisión sin blancos y ranking antes del redondeo; nombre del último snapshot propio.
- Verificación: `RunDatabaseTest.ps1 -Progress` — OK (HTTP, Chromium y restauración); `npm.cmd run lint`, `npm.cmd run test:run` (54 pruebas) y `npm.cmd run build` — OK.
- Documentación: contrato HTTP/OpenAPI, README, arquitectura, persistencia, planes e índice actualizados; evidencia en `../auditoria-mvp.md`.

## [x] T081 - Integración React

- Probar panel principal y página de estadísticas contra PHP.

**Comprobación:** las métricas cambian tras nuevas entregas y muestran estados
vacíos comprensibles.

**Cierre — 2026-10-11**

- Implementado: Panel y ranking React contra PHP; métricas actualizadas tras entregas, vacíos, errores y pantalla móvil.
- Verificación: `RunDatabaseTest.ps1 -Progress` — OK (HTTP, Chromium y restauración); `npm.cmd run lint`, `npm.cmd run test:run` (54 pruebas) y `npm.cmd run build` — OK.
- Documentación: contrato HTTP/OpenAPI, README, arquitectura, persistencia, planes e índice actualizados; evidencia en `../auditoria-mvp.md`.
