# 04. Gestión de preguntas

Estado: completado localmente (P07, 2/2). Dependencia P06 verificada.

## [x] T030 - API de preguntas

- Implementar lista paginada, filtros, búsqueda, alta, edición y cambio de
  estado con cuatro opciones y una única correcta.
- Guardar pregunta y opciones en una transacción; conservar IDs al editar.
- Impedir alta, edición hacia un tema inactivo y reactivación si el tema está
  inactivo. La lista es administrativa; generación de tests corresponde a P09.

**Comprobación:** entradas inválidas no dejan filas parciales; paginación y
filtros coinciden con `../diseno-api.txt`.

**Cierre — 2026-10-11**

- Implementado: cinco rutas autenticadas, filtros por tema/estado/texto,
  paginación, validación Unicode de enunciado y opciones, posiciones 1–4 y
  exactamente una correcta. Alta y edición atómicas; opciones actualizadas
  por posición sin recrear sus IDs. Estado lógico sin modificar snapshots.
- Verificación: `RunDatabaseTest.ps1 -Questions` — OK en PHP 8.3 y MariaDB
  10.4.32 de XAMPP. HTTP cubre 400/404/409/415, CSRF, límites Unicode,
  búsqueda literal, páginas, banco compartido y snapshots reales con sus FK.
  Fallos forzados en la tercera opción prueban rollback al crear y editar.
  Sintaxis PHP, núcleo HTTP y copia/restauración — OK.
- Documentación: contrato, README, arquitectura, plan, índice y auditoría
  actualizados. Esquema existente reutilizado, sin cambios de persistencia.

## [x] T031 - Integración React

- Probar lista y formularios copiados contra PHP, incluidos errores y estados
  vacíos.

**Comprobación:** alta, búsqueda, edición y activación desde navegador.

**Cierre — 2026-10-11**

- Implementado: recorrido Chromium de lista y formularios existentes contra
  PHP, sin cambios de producto en React. Runner `-Questions` con cuentas y
  bases temporales aisladas.
- Verificación: `RunDatabaseTest.ps1 -Questions` — OK, incluye vacío, carga,
  error de listado, validación de PHP y foco de campo, teclado, alta, búsqueda,
  filtro por tema, edición/correcta, conservación de filtros, cancelación y
  confirmación al desactivar, reactivación y persistencia tras recarga.
  `RunDatabaseTest.ps1 -Topics` y `-Authentication` — OK; lint, 54 pruebas React
  y build — OK. `git diff --check` — OK.
- Documentación: comando de reproducción y resultados registrados. P08 pendiente.
