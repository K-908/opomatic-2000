# 03. Gestión de temas

Estado: completado localmente (P06, 2/2). Dependencias P03 y P05 verificadas.

## [x] T020 - API de temas

- Implementar lista, detalle, alta, edición, desactivación y reactivación según
  `../diseno-api.txt`.
- Normalizar nombre, impedir duplicados y distinguir 400, 404 y conflicto.
- No eliminar físicamente temas usados por preguntas o histórico.

**Comprobación:** rutas, validaciones, unicidad y exclusión de temas inactivos
probadas con MariaDB 10.4.32 de XAMPP y PHP 8.3.

**Cierre — 2026-10-11**

- Implementado: cinco rutas autenticadas, nombre Unicode de 1–150 caracteres
  con espacios exteriores eliminados, unicidad garantizada por MySQL/MariaDB,
  contratos JSON y conteo de preguntas activas. Estado lógico sin alterar preguntas.
- Verificación: `RunDatabaseTest.ps1 -Topics` — OK; prueba HTTP comprueba
  201/Location, 204, 400/404/409, CSRF, filtros, Unicode y conservación de preguntas;
  `RunDatabaseTest.ps1 -Authentication` — OK; núcleo HTTP y sintaxis PHP — OK.
- Documentación: contrato, README, arquitectura, plan e índice actualizados.

## [x] T021 - Integración React

- Ejecutar la pantalla ya copiada contra la API PHP.
- Verificar estados de carga, vacío, error, teclado y recorrido feliz.

**Comprobación:** crear, editar y cambiar estado desde Chromium contra PHP.

**Cierre — 2026-10-11**

- Implementado: pantalla existente compatible sin cambios de producto; recorrido
  Playwright dedicado y runner con cuentas aleatorias y bases temporales.
- Verificación: `RunDatabaseTest.ps1 -Topics` — OK, incluye Chromium real para
  carga, vacío, error, teclado, duplicado, persistencia tras recarga, edición,
  cancelación/confirmación de desactivación, filtro y reactivación;
  `npm.cmd run lint`, `npm.cmd run test:run` (54 pruebas), `npm.cmd run build` — OK.
- Documentación: auditoría y comando de reproducción actualizados. P07 pendiente.
