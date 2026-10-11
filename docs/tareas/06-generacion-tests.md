# 06. Generación de tests

Estado: completado localmente (P09, 2/2). Dependencias P07 y P05 verificadas.

## [x] T050 - Disponibilidad y selección

- Contar preguntas activas de temas activos y seleccionar sin repetición.
- Barajar preguntas y opciones con aleatoriedad apropiada.
- Responder sin revelar `isCorrect`.

**Comprobación:** cantidad, temas, ausencia de repetidos y error por
insuficiencia coinciden con `../diseno-api.txt`.

**Cierre — 2026-10-11**

- Implementado: `POST /api/tests/generate` con sesión y CSRF; validación estricta
  de IDs enteros, temas sin repetir y cantidad de 1–200. Selección de preguntas
  activas en temas activos, barajado Fisher–Yates con `random_int()` y cuatro
  opciones barajadas. Lecturas en una transacción consistente; sin guardar
  intentos y sin consultar ni exponer la respuesta correcta.
- Verificación: `RunDatabaseTest.ps1 -Generation` — OK en PHP 8.3 y MariaDB
  10.4.32. HTTP prueba 1 y 200 preguntas, bancos combinados, exclusión de
  preguntas inactivas y temas ajenos, ausencia de repetidos, tipos JSON,
  400/401/403/404 y 409 con `requestedCount`/`availableCount`, incluso cero.
  Identificadores y órdenes de pregunta/opción coinciden con el token.
- Chromium `generation.spec.ts` — OK: temas disponibles, validación, insuficiencia
  real tras cambiar el banco, estado Preparando, presentación en orden,
  navegación conservando respuesta, dejar sin contestar, abandono y recarga
  perdiendo el test; sin datos en localStorage/sessionStorage.
- Regresiones `-Import`, `-Questions`, `-Topics`, `-Authentication` — OK.
  Copias/restauraciones verificadas y bases/servidores temporales eliminados.
  Sintaxis PHP, núcleo HTTP, lint, 54 pruebas React y build — OK.
- Documentación: contrato, README, arquitectura, plan, índice y auditoría
  actualizados. Esquema y frontend de producto conservados.

## [x] T051 - Token temporal

- Firmar/autenticar el token con clave privada, usuario y expiración.
- Rechazar alteración, uso cruzado de cuentas y caducidad.

**Comprobación:** pruebas de token válido, manipulado y caducado.

**Cierre — 2026-10-11**

- Implementado: token opaco versionado, cifrado/autenticado AES-256-GCM,
  nonce aleatorio de 12 bytes y tag de 16; clave derivada de `APP_KEY`
  mediante HKDF-SHA256 con propósito exclusivo. Exige al menos 32 bytes
  de secreto privado, sin valor por defecto. Usuario, IDs y orden protegidos;
  vencimiento absoluto a las doce horas. OpenSSL declarado en Composer.
- Verificación: `composer test:tokens` — OK: verificación independiente,
  nonce distinto, opacidad, clave equivocada/ausente/corta, alteración de
  nonce/tag/ciphertext, tokens truncados o malformados, claims inválidos,
  dos cuentas y caducidad en el segundo exacto con reloj controlado.
  La prueba HTTP verifica tokens emitidos por ambas sesiones reales.
- El verificador distingue token inválido, cuenta ajena y expiración para
  su uso en P10. La entrega/corrección se implementará en esa tarea.
