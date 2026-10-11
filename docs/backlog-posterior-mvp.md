# Backlog posterior al MVP de OpoMatic-2000

Lista heredada como referencia funcional. En este repositorio el port PHP está
pendiente; ninguno de estos elementos supone que el MVP PHP ya esté terminado.

Este documento recoge trabajo deliberadamente excluido del cierre inicial. Cada
elemento deberá convertirse en historias de usuario y criterios de aceptación
antes de implementarse.

## Prioridad alta

- Guardado y recuperación de tests en curso.
- Límites de tiempo configurables y comportamiento al agotarse.
- Repetición guiada de preguntas falladas y tests formados solo por fallos.

## Prioridad media

- Evolución temporal mediante gráficos y comparativas por periodos.
- Notas o explicaciones asociadas a preguntas.
- Objetivos semanales y seguimiento de cumplimiento.
- Pulido específico para móvil y pruebas en más motores de navegador.

## Prioridad baja o condicionada

- Recordatorios por Telegram, SMS, WhatsApp u otros servicios.
- Roles, permisos y recuperación de contraseñas si se amplía el modelo de
  usuarios.
- Protección frente a abuso, rate limiting y despliegue público endurecido.
- Optimización mediante vistas o resúmenes solo si aparecen volúmenes que lo
  justifiquen.

## Limitaciones conocidas del MVP

- Dos cuentas cerradas, sin registro público ni recuperación de contraseña.
- El despliegue público requiere HTTPS, cookies seguras y configuración de
  producción antes de aceptar credenciales.
- Los tests en curso solo viven en memoria y se pierden al cerrar o recargar.
- Sin temporizador, repetición automática, notas, objetivos ni recordatorios.
- El diseño es utilizable desde 320 px, pero no pretende ser todavía una
  experiencia móvil completamente pulida.
