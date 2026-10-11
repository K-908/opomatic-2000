# 08. Histórico y revisión

Estado: pendiente (P11). El detalle desde snapshots y su aislamiento se
implementaron y probaron en P10 para completar la entrega de React.
Quedan el listado/paginación y sus recorridos de integración.

## T070 - Consultas por cuenta

- Implementar lista paginada descendente y detalle de intento desde snapshots.
- Aislar datos por usuario autenticado; devolver 404 para intento ajeno.

**Comprobación:** edición o desactivación posterior de una pregunta no altera
la revisión histórica; una cuenta no ve intentos de la otra.

## T071 - Integración React

- Verificar lista, paginación, detalle, vacíos y errores.

**Comprobación:** histórico navegable con las respuestas correctas y elegidas.
