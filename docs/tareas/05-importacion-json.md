# 05. Importación JSON

Estado: completado localmente (P08, 2/2). Dependencia P07 verificada.

## [x] T040 - Validación e importación

- Conservar contrato JSON y límites de 5 MB y 2.000 preguntas.
- Informar errores por ruta; crear o reutilizar temas activos.
- Importar todo en una transacción, sin resultados parciales.

**Comprobación:** un archivo válido crea las filas previstas; cualquier error
revierte todas; un tema inactivo produce el conflicto documentado.

**Cierre — 2026-10-11**

- Implementado: `POST /api/questions/import` autenticado con CSRF; lectura
  limitada a 5 MB más un byte, documento validado antes de guardar, errores
  precisos por ruta y resumen 201. Nombres comparados con la colación real;
  temas nuevos/reutilizados y preguntas/opciones en una sola transacción.
- Verificación: `RunDatabaseTest.ps1 -Import` — OK en PHP 8.3 y MariaDB 10.4.32.
  HTTP verifica 400/409/413/415, sesión/CSRF, duplicados Unicode, reutilización
  sin modificar datos del tema, conflicto inactivo, 5 MB exactos y un byte de
  exceso, 2.000 preguntas en uno o 2.000 temas y rechazo de 2.001. Un trigger
  temporal fuerza un fallo después de guardar preguntas y prueba rollback
  completo de temas, preguntas y opciones. Sintaxis PHP y núcleo HTTP — OK.
- Documentación: contrato, README, arquitectura, plan, índice y auditoría
  actualizados. Esquema reutilizado sin cambios.

## [x] T041 - Integración React

- Comprobar previsualización, errores y resumen de importación en la pantalla
  copiada.

**Comprobación:** importación y rechazo de archivo inválido desde navegador.

**Cierre — 2026-10-11**

- Implementado: formulario existente compatible con PHP, sin cambios de
  producto en React. Recorrido `import.spec.ts` y runner `-Import` aislado.
- Verificación: Chromium — OK para extensión/tamaño/JSON inválidos,
  previsualización, errores de validación y conflicto con rutas, estado de
  importación, temas creados/reutilizados, repetición acumulativa y revisión
  de preguntas/correcta. Regresiones `-Questions`, `-Topics` y `-Authentication`
  — OK; copia/restauración y limpieza de bases temporales — OK; lint, 54 pruebas
  React y build — OK; `git diff --check` — OK.
- Documentación: pruebas y reproducción registradas. P09 pendiente.
