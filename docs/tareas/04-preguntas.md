# 04. Gestión de preguntas

Estado: pendiente.

## T030 - API de preguntas

- Implementar lista paginada, filtros, búsqueda, alta, edición y cambio de
  estado con cuatro opciones y una única correcta.
- Guardar pregunta y opciones en una transacción; conservar IDs al editar.
- Impedir uso de preguntas inactivas o pertenecientes a temas inactivos.

**Comprobación:** entradas inválidas no dejan filas parciales; paginación y
filtros coinciden con `../diseno-api.txt`.

## T031 - Integración React

- Probar lista y formularios copiados contra PHP, incluidos errores y estados
  vacíos.

**Comprobación:** alta, búsqueda, edición y activación desde navegador.
