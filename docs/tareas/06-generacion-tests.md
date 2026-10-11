# 06. Generación de tests

Estado: pendiente.

## T050 - Disponibilidad y selección

- Contar preguntas activas de temas activos y seleccionar sin repetición.
- Barajar preguntas y opciones con aleatoriedad apropiada.
- Responder sin revelar `isCorrect`.

**Comprobación:** cantidad, temas, ausencia de repetidos y error por
insuficiencia coinciden con `../diseno-api.txt`.

## T051 - Token temporal

- Firmar/autenticar el token con clave privada, usuario y expiración.
- Rechazar alteración, uso cruzado de cuentas y caducidad.

**Comprobación:** pruebas de token válido, manipulado y caducado.
