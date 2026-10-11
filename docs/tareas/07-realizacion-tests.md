# 07. Realización y corrección

Estado: pendiente.

## T060 - Corrección

- Validar token, opciones seleccionadas y pertenencia a preguntas.
- Centralizar puntuación: acierto `10/N`, fallo `-(10/N)*0,25`, blanco `0`.
- Evitar redondeos intermedios; guardar `Score` en millonésimas.

**Comprobación:** aciertos, fallos, blancos y notas negativas con casos límite.

## T061 - Entrega

- Guardar intento y snapshots en una transacción.
- Usar `submissionId` único para idempotencia.
- Conservar nota, contadores y resultado por pregunta.

**Comprobación:** repetir petición no duplica; fallo a mitad revierte todo.

## T062 - Integración React

- Probar navegación, selección, confirmación de salida y resultado final.

**Comprobación:** recorrido completo desde `/tests/new` hasta el detalle.
