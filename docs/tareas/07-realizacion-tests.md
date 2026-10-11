# 07. Realización y corrección

Estado: completado localmente (P10, 3/3). Dependencias P09 y P03 verificadas.

## [x] T060 - Corrección

- Validar token, opciones seleccionadas y pertenencia a preguntas.
- Centralizar puntuación: acierto `10/N`, fallo `-(10/N)*0,25`, blanco `0`.
- Evitar redondeos intermedios; guardar `Score` en millonésimas.

**Comprobación:** aciertos, fallos, blancos y notas negativas con casos límite.

**Cierre — 2026-10-11**

- Implementado: `POST /api/tests/finish` con sesión/CSRF, UUID no vacío
  normalizado, token vigente de la cuenta autenticada y una respuesta por
  pregunta. IDs enteros positivos, opción propia o null explícito; rechazo
  de ausencias, duplicados y opciones ajenas. Verificación de preguntas y
  cuatro opciones persistidas, con una correcta.
- Nota centralizada en `AttemptScorer` con aritmética racional entera exacta:
  `(4*A-F)*2.500.000/N` en millonésimas, sin redondeos intermedios.
  Solo el resultado final se redondea a la millonésima más próxima, con
  mitades alejándose de cero; regla 1 persistida. Clasificación 0 blanco,
  1 acierto, 2 fallo; conversión a puntos únicamente para el contrato HTTP.
- Verificación: `composer test:scoring` — OK para todos los contadores válidos
  de N=1–200, máximos/mínimos/blancos, decimales periódicos, mitades positivas
  y negativas, contadores inválidos y clasificación.
- `RunDatabaseTest.ps1 -Completion` — OK en PHP 8.3 y MariaDB 10.4.32:
  HTTP prueba 400/401/403/409/410/415, tipos, tokens alterados/caducados/cruzados,
  preguntas u opciones que no corresponden, nota mixta, extremos de una
  pregunta y entrega de 200. Score y ScoringRuleVersion comprobados en SQL.

## [x] T061 - Entrega

- Guardar intento y snapshots en una transacción.
- Usar `submissionId` único para idempotencia.
- Conservar nota, contadores y resultado por pregunta.

**Comprobación:** repetir petición no duplica; fallo a mitad revierte todo.

**Cierre — 2026-10-11**

- Implementado: intento, temas, preguntas y cuatro opciones por pregunta
  guardados atómicamente, conservando el orden generado, texto, resultado,
  selección, correcta, fecha UTC, usuario, contadores y nota. Se reutilizan
  las tablas existentes, sin cambios de esquema.
- Primera entrega devuelve 201 y Location; repetición compatible, 200 y el
  resultado original. Mismo UUID con respuestas/test/usuario diferentes
  produce 409. Comparación desde snapshots, sin volver a puntuar el banco
  actual; orden de la lista answers y mayúsculas del UUID son equivalentes.
- Un conflicto concurrente de la clave única revierte la transacción y lee
  el intento ganador fuera del snapshot anterior antes de comprobarlo.
- Verificación: repetición exacta, respuestas reordenadas, UUID normalizado,
  conflicto con otro test/otras respuestas/otra cuenta y replay después de
  editar el banco — OK. Dos procesos PHP compiten realmente en MariaDB:
  uno crea, otro recupera; un único intento con todos sus snapshots.
- Un trigger temporal falla en la segunda pregunta tras guardar la primera:
  rollback completo de las cuatro tablas de histórico; respuesta 500 genérica
  con traceId; reintento posterior correcto. Copia/restauración y limpieza — OK.

## [x] T062 - Integración React

- Probar navegación, selección, confirmación de salida y resultado final.

**Comprobación:** recorrido completo desde `/tests/new` hasta el detalle.

**Cierre — 2026-10-11**

- Implementado: frontend existente compatible sin cambios de producto.
  `GET /api/attempts/{id}` devuelve snapshots solo del usuario autenticado;
  intento ajeno o ausente devuelve 404. Esta lectura es necesaria para el
  resultado final de React; listado/paginación del histórico quedan en P11.
- Chromium `completion.spec.ts` — OK: generación real, acierto/fallo/blanco,
  navegación conservando selección, cancelación de abandono, confirmación
  con aviso de blancos, foco/Escape, nota 2.50 y revisión por pregunta.
- La primera entrega se guarda en PHP y se pierde su respuesta al navegador:
  React conserva UUID, token y respuestas, reintenta, recibe 200 y muestra el
  mismo intento. Durante el envío no permite otra entrega.
- Edición/desactivación posterior no cambia texto, opciones, correcta, nota
  ni selección de la revisión; detalle persistente tras recarga. HTTP verifica
  aislamiento con dos cuentas y permite entregar un token vigente tras
  desactivar lógicamente su contenido.
- Regresiones `-Generation`, `-Questions` y `-Authentication` — OK.
  Sintaxis PHP, núcleo HTTP, tokens, Composer y `git diff --check` — OK.
  Frontend lint, 54 pruebas en 14 archivos y build — OK.
- Documentación: README, contrato, arquitectura, diseño de base de datos,
  plan, índice y auditoría actualizados. P11 es la siguiente tarea.
