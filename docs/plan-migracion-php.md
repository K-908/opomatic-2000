# Lista de tareas para migrar OpoMatic-2000 a PHP

Estado: migración en curso. **P00 está en curso, P01–P12 están completadas y
P13–P14 están pendientes**. El frontend React y el esquema inicial MySQL ya
están copiados; PHP responde a `GET /health` y dispone de un núcleo HTTP y
acceso PDO a MySQL.
Se parte de una base vacía y no se usará Docker.

El objetivo es conservar las rutas, los cuerpos JSON y el comportamiento de
[`diseno-api.txt`](diseno-api.txt), para poder reutilizar React. Cada tarea debe
entregar código, pruebas proporcionadas al riesgo y documentación actualizada.
Las dependencias indican qué debe estar operativo antes de cerrar la tarea.

| ID | Tarea y resultado verificable | Depende de |
|---|---|---|
| P00 | **Inventariar entorno:** comprobación local terminada y requisitos anotados en `entorno-hosting.md`; queda verificar el proveedor elegido e importar allí el esquema en una base de prueba. Estado: en curso. | — |
| P01 | **Arranque PHP:** `composer.json`, autoload, configuración local no versionada, front controller y `GET /health`. Completada: responde JSON desde PHP sin Docker. | — |
| P02 | **Capa HTTP:** completada; router preparado para `/api`, lectura y validación básica de JSON, estados HTTP y `application/problem+json`. Probados 400, 404, 405 y errores inesperados. | P01 |
| P03 | **Acceso MySQL:** completada en MySQL 8.0.46 local. Conexión PDO, consultas preparadas, transacciones, repositorios y comprobación de claves/restricciones de las nueve tablas; copia restaurada en otra base temporal. Falta repetir la compatibilidad en el hosting elegido (P00/P13). | P01 |
| P04 | **Alta inicial de cuentas:** completada y probada en MySQL local. Comando PHP CLI de una sola ejecución para crear `Gogloglo` y `Virginia` con `password_hash()`, sin contraseñas en Git ni en SQL; una inserción fallida revierte ambas. Su ejecución definitiva queda para el despliegue. | P03 |
| P05 | **Autenticación:** completada en local. Login, sesión de hasta 12 horas, logout, cookies, CSRF, cinco intentos por IP/minuto y `/auth/accesses` (máximo 50). Probado por HTTP con dos cuentas aisladas. El aislamiento del detalle, listado y estadísticas está verificado en P10–P12. | P02, P04 |
| P06 | **Temas:** completada localmente. Lista, detalle, alta, edición y estado lógico con validación Unicode, nombres únicos y conteo de preguntas activas. HTTP y Chromium contra PHP/MariaDB verificados, incluida la pantalla React. | P03, P05 |
| P07 | **Preguntas:** completada localmente. CRUD lógico, filtros, búsqueda literal, paginación, cuatro opciones y una correcta. Escritura transaccional con IDs conservados y rollback probado; snapshots intactos. Lista y formularios React verificados en Chromium contra PHP/MariaDB. | P06 |
| P08 | **Importación JSON:** completada localmente. Límites de 5 MB y 2.000 preguntas, errores por ruta, temas nuevos/reutilizados y transacción única. Rollback forzado y límites exactos probados; formulario React verificado en Chromium contra PHP/MariaDB. | P07 |
| P09 | **Generación de tests:** completada localmente. Selección de 1–200 preguntas activas sin repetidos y barajado seguro. Token opaco AES-256-GCM ligado a usuario y doce horas; manipulación, caducidad y cuentas cruzadas probadas. HTTP y Chromium verificados sin exponer respuestas correctas. | P07, P05 |
| P10 | **Corrección y entrega:** completada localmente. Puntuación exacta en millonésimas, validación de token/respuestas y snapshots atómicos. Idempotencia, carrera y rollback probados; recorrido React hasta el detalle inmutable por cuenta verificado en Chromium. | P09, P03 |
| P11 | **Histórico:** completado localmente. Lista paginada consistente por cuenta, orden fecha/ID, validación y snapshots inmutables. HTTP y Chromium verifican navegación, vacíos, errores, teclado y móvil. | P10 |
| P12 | **Estadísticas:** completadas localmente. Resumen y ranking por cuenta desde snapshots, precisión sin blancos, empates y redondeo. Panel y página React verificados; recorrido integral, copia y restauración temporal OK. | P11 |
| P13 | **Publicación Apache:** compilar React con `VITE_API_BASE_URL=/`, servir `dist`, enviar `/api/*` y `/health` a PHP y las rutas SPA a `index.html`. Mantener `src`, configuración, dependencias y copias fuera del directorio público. | P00, P02, P05 |
| P14 | **Verificación integral:** comparar todas las rutas y errores con el contrato; ejecutar pruebas PHP/MySQL, lint, Vitest, build y Playwright contra instalación local aislada. Revisar HTTPS, cookies, CSRF, límites, copias y restauración. Registrar evidencia en `auditoria-mvp.md`. | P06–P13 |

## Orden de trabajo

1. P00 puede realizarse en paralelo con P01–P02. La elección de hosting no
   bloquea el desarrollo local; sus datos concretos sí se necesitan para P13.
2. P03–P05 forman la base de persistencia y seguridad antes de exponer datos.
3. P06–P12 se implementan por funcionalidad, verificando PHP y React juntos.
4. P13 puede prepararse tras el primer endpoint, pero se cierra al probar la
   publicación completa.
5. P14 cierra la migración. Solo entonces se considera utilizable el port PHP.

## Criterio común de cierre

- Las rutas afectadas conservan método, estado, JSON y errores del contrato.
- Las consultas usan parámetros; las operaciones con varias tablas son atómicas.
- Los datos privados se separan por usuario y las contraseñas no aparecen en
  respuestas, logs ni archivos versionados.
- Las pruebas relevantes pasan y se documenta el resultado real.
- La tarea y el índice de `tareas/` se actualizan sin atribuir a PHP pruebas de
  la versión .NET.

Los archivos de [`tareas/`](tareas/README.md) amplían cada bloque funcional.
