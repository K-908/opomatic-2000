# Lista de tareas para migrar OpoMatic-2000 a PHP

Estado: planificación. **P00 está en curso, P01–P05 están completadas y
P06–P14 están pendientes**. El frontend React y el esquema inicial MySQL ya
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
| P05 | **Autenticación:** completada en local. Login, sesión de hasta 12 horas, logout, cookies, CSRF, cinco intentos por IP/minuto y `/auth/accesses` (máximo 50). Probado por HTTP con dos cuentas aisladas. El aislamiento de intentos y estadísticas se comprobará al crear esas rutas en P11/P12. | P02, P04 |
| P06 | **Temas:** lista, detalle, alta, edición y activación/desactivación con validación y nombres únicos. La pantalla React debe funcionar contra PHP. | P03, P05 |
| P07 | **Preguntas:** CRUD lógico, filtros, búsqueda, paginación, cuatro opciones y una correcta; escritura transaccional. Probar las pantallas existentes. | P06 |
| P08 | **Importación JSON:** validar tamaño, cantidad y rutas de error; crear o reutilizar temas y guardar todo o nada. Probar el formulario React. | P07 |
| P09 | **Generación de tests:** disponibilidad, selección sin repetidos, barajado y token firmado con usuario y caducidad, sin exponer respuestas correctas. | P07, P05 |
| P10 | **Corrección y entrega:** calcular nota en un único servicio, validar respuestas, guardar snapshots en una transacción y hacer idempotente `submissionId`. Probar `/tests/run`. | P09, P03 |
| P11 | **Histórico:** lista paginada y detalle reconstruido de snapshots; impedir acceso a intentos de otra cuenta. Probar lista y revisión React. | P10 |
| P12 | **Estadísticas:** resumen y ranking por tema desde snapshots, con casos vacíos y sin divisiones por cero. Probar panel y página React. | P11 |
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
