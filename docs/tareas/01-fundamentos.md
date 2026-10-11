# 01. Fundamentos PHP

Estado: T001 y T002 completadas.

## [x] T001 - Estructura ejecutable

- Crear `composer.json`, autoload y punto de entrada en `backend/public`.
- Preparar carpetas `Http`, `Application`, `Domain` e `Infrastructure`. Las
  dependencias reales se concretarán al implementar casos de uso.
- Comprobar PHP local; la verificación del hosting elegido pertenece a P00.

**Comprobación:** instalación reproducible sin Docker; PHP carga clases y
responde a una petición local.

**Cierre — 2026-10-11**

- Implementado: Composer con autoload PSR-4, lectura de `APP_ENV`, punto de
  entrada y router para el servidor PHP local; `GET /health` devuelve JSON.
- Verificación: `composer validate --strict` — OK; `composer install` — OK;
  `php -l` — OK en los cuatro archivos; petición HTTP real a `/health` — 200,
  JSON y contenido esperado; ruta desconocida — 404.
- Documentación: README, contrato API, arquitectura y plan actualizados.

## [x] T002 - Contrato base

- Conservar `GET /health` y generalizar JSON, errores Problem Details y logging
  privado para el resto de rutas.
- Configurar rutas `/api/*`, rechazo de métodos no admitidos y parámetros.
- Preparar pruebas HTTP que verifiquen estado, contenido y errores.

**Comprobación:** salud, 404, 405 y error de validación responden según
`../diseno-api.txt`.

**Cierre — 2026-10-11**

- Implementado: `Request`, `Response`, `Router` y `Kernel` con parámetros de
  ruta/consulta, lectura de objetos JSON, 204 vacío, Problem Details, `Allow`
  para 405 y registro privado de errores inesperados.
- Verificación: `composer test:http` — OK (200, 201, 204, 400, 404, 405, 415 y
  500); `php -l` — OK; servidor PHP real — `/health` 200, ruta inexistente 404,
  método no admitido 405; `composer validate --strict` — OK.
- Documentación: README, diseño de API, arquitectura y plan actualizados.
