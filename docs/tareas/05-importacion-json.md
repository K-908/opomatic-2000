# 05. Importación JSON

Estado: pendiente.

## T040 - Validación e importación

- Conservar contrato JSON y límites de 5 MB y 2.000 preguntas.
- Informar errores por ruta; crear o reutilizar temas activos.
- Importar todo en una transacción, sin resultados parciales.

**Comprobación:** un archivo válido crea las filas previstas; cualquier error
revierte todas; un tema inactivo produce el conflicto documentado.

## T041 - Integración React

- Comprobar previsualización, errores y resumen de importación en la pantalla
  copiada.

**Comprobación:** importación y rechazo de archivo inválido desde navegador.
