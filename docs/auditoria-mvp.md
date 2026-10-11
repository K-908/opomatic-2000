# Auditoría del MVP de OpoMatic-2000

## Estado

Pendiente. El MVP de OpoMatic-3000 fue auditado en su repositorio, pero esa
evidencia no demuestra que el port PHP cumpla los mismos requisitos. El
frontend está copiado; PHP responde a `GET /health` y tiene infraestructura
HTTP, acceso MySQL, autenticación, banco, importación, generación y entrega
de tests y detalle desde snapshots. Listado del histórico y estadísticas
siguen pendientes.

## Comprobaciones para el cierre

| Área | Evidencia necesaria en OpoMatic-2000 | Estado |
|---|---|---|
| Contrato HTTP | Rutas, estados, JSON y Problem Details iguales a `diseno-api.txt` | Pendiente |
| MySQL | Esquema, claves y transacciones probadas en la versión del hosting | Pendiente |
| Cuentas | Hashes PHP, sesiones, CSRF, límite de login y separación de datos | Autenticación, accesos y detalle de intento aislados localmente; listado/estadísticas pendientes |
| Tests | Selección, token, puntuación, idempotencia y snapshots | Selección, token, corrección, idempotencia y snapshots verificados localmente (P09–P10) |
| Frontend | Lint, pruebas, build y recorrido E2E contra PHP | Pendiente |
| Hosting | HTTPS, rutas Apache, secretos privados, copias y restauración | Pendiente |

En MySQL 8.0.46 local, la prueba P03 importó el esquema, verificó nueve tablas,
10 FK y 10 CHECK, probó repositorios, consultas preparadas, commit y rollback,
y restauró una copia en otra base temporal. Esto valida la infraestructura de
acceso, no los endpoints PHP ni el hosting definitivo.

P04 verificó en otra base temporal la creación única de `Gogloglo` y
`Virginia`, hashes comprobados con `password_verify()`, rechazo de repetición y
rollback ante un fallo en la segunda inserción. P05 verificó por HTTP los dos
logins, cookies, CSRF, expiración, logout, límite por IP y registros de acceso
aislados. Aún falta verificar aislamiento de intentos y estadísticas cuando
existan esas rutas y comprobar HTTPS/cookies en el hosting real.

Consulta `requisitos.txt` para historias y reglas de negocio. Al cerrar el
port, registra aquí resultados reales y versiones del entorno comprobado.

## Revisión de P05 — 2026-10-11

La revisión del código y del contrato no detectó partes pendientes en P05:
login, sesión, logout, cookies, CSRF, límite por IP y accesos por cuenta están
implementados. P05 conserva su estado de completada localmente; P06 es la
siguiente tarea pendiente.

- Preparación: dependencias PHP y frontend instaladas desde sus lockfiles.
- `php -n backend/tests/Integration/HttpKernelTest.php`: OK.
- Sintaxis de todos los archivos PHP del backend: OK.
- HTTP con servidor PHP temporal: `/health` devuelve 200; sesión y accesos
  sin autenticación devuelven 401.
- Frontend: `npm.cmd run lint`, `npm.cmd run test:run` (54 pruebas en 14
  archivos) y `npm.cmd run build`: OK.
- No se repitió `RunDatabaseTest.ps1 -Authentication`: MySQL no está disponible
  en PATH, no se encontró un servicio MySQL y no está definida la contraseña
  de administración de pruebas. La evidencia anterior con MySQL 8.0.46 se
  conserva y no se presenta como una prueba ejecutada en esta revisión.
- Entorno: el PHP de Herd avisa de extensiones Redis/MongoDB ausentes;
  la prueba del núcleo HTTP y el servidor temporal se ejecutaron con `-n`.
  npm avisa de que Node 23.11.0 no está entre las versiones admitidas por
  varias dependencias, aunque las comprobaciones del frontend pasaron.

## Verificación de P05 con XAMPP — 2026-10-11

`RunDatabaseTest.ps1 -AdminUser root -Authentication`: OK, con MariaDB
10.4.32 de XAMPP y PHP 8.3 de Herd. Se usó una configuración PHP temporal
con PDO MySQL, cURL y OpenSSL, sin modificar la configuración global.

- Base temporal: nueve tablas, 10 claves foráneas, 10 restricciones CHECK,
  conexión utf8mb4, repositorios y transacciones: OK.
- HTTP: login de Gogloglo y Virginia con contraseñas aleatorias, sesiones
  independientes, caducidad a las doce horas, logout, CSRF, sexto intento
  limitado con 429 y accesos UTC separados por usuario: OK.
- Proxy Vite: `/health` 200 y `/api/auth/session` anónimo 401: OK.
- Copia y restauración en otra base temporal: nueve tablas OK.
- Ambas bases temporales se eliminaron al finalizar. No se crearon cuentas
  definitivas ni se modificaron bases existentes.

P05 queda confirmada como completada en local también con MariaDB 10.4.32.
El XAMPP instalado incluye PHP 8.0.30, que no cumple PHP 8.2+ requerido por
el proyecto: Apache con ese PHP no puede ejecutar el backend sin actualizarlo
o configurar un PHP compatible. La prueba utilizó PHP 8.3 y su servidor local.

## Cierre de P06 — 2026-10-11

P06 completada en PHP 8.3 y MariaDB 10.4.32 de XAMPP. Se implementaron las
cinco rutas de temas, validación Unicode, nombres únicos, filtros, conteo de
preguntas y cambios de estado lógico. La pantalla React existente funciona
contra PHP sin cambios de producto.

- `RunDatabaseTest.ps1 -Topics`: OK, rutas y validaciones HTTP más un recorrido
  Chromium de carga, vacío, error, teclado, crear/editar, duplicado,
  cancelación/confirmación al desactivar, filtro, reactivar y recarga.
- `RunDatabaseTest.ps1 -Authentication`: OK, regresión de P05.
- Ambos runners verificaron persistencia, restricciones, transacciones y
  copia/restauración en bases temporales; eliminaron sus bases al terminar.
- Núcleo HTTP y sintaxis de todos los archivos PHP: OK.
- Frontend: lint, 54 pruebas en 14 archivos y build: OK.

El error de carga se simula solo en el navegador mediante interceptación;
el resto del recorrido utiliza la API PHP y MariaDB reales. El aislamiento
futuro de intentos/estadísticas y la publicación siguen pendientes. P07 es
la siguiente tarea, sin implementar sus rutas en P06.

## Cierre de P07 — 2026-10-11

P07 completada localmente en PHP 8.3 y MariaDB 10.4.32 de XAMPP. Gestión de
preguntas con lista paginada, filtros, búsqueda literal, detalle con opciones,
alta/edición transaccional y cambio de estado lógico. Validación Unicode y
cuatro opciones con posiciones distintas y una única correcta.

- `RunDatabaseTest.ps1 -Questions`: OK. Validaciones y 400/404/409/415, sesión y
  CSRF, filtros, paginación, límites Unicode, banco compartido entre cuentas,
  edición conservando IDs y estado de temas. Fallos forzados mediante triggers
  temporales en la tercera opción verificaron rollback completo en alta y
  edición. Snapshots con sus FK permanecieron intactos tras editar y desactivar.
- Chromium `questions.spec.ts`: OK. Lista vacía, carga, error, validación real
  de PHP y foco, teclado, alta, búsqueda, filtro por tema, edición/correcta,
  retorno conservando filtros, confirmación/cancelación de desactivación,
  reactivación y persistencia tras recarga.
- Regresiones `RunDatabaseTest.ps1 -Topics` y `-Authentication`: OK.
- Los tres modos verificaron persistencia y copia/restauración y eliminaron
  sus bases temporales al terminar; no se crearon cuentas definitivas.
- Sintaxis PHP, núcleo HTTP y `git diff --check`: OK.
- Frontend: lint, 54 pruebas en 14 archivos y build: OK.

En Chromium se simularon demora y error de listado; el resto usó PHP/MariaDB
reales. Para probar el error de campo se alteró una opción enviada a PHP y se
comprobó su respuesta 400 en React. Importación P08 y generación P09 siguen
pendientes. No se cambiaron tablas ni se añadieron rutas de esas tareas.

## Cierre de P08 — 2026-10-11

Importación JSON completada en PHP 8.3 y MariaDB 10.4.32 de XAMPP. Ruta
protegida con sesión/CSRF, lectura limitada, validación previa, reutilización
atómica de temas activos, transacción única y resumen 201 compatible con React.
El esquema no cambia y no se crean rutas de generación/entrega de tests.

- `RunDatabaseTest.ps1 -Import`: OK. Documentos inválidos, rutas de errores,
  400/409/413/415, sesión/CSRF, nombres duplicados Unicode según colación,
  temas nuevos/reutilizados, conservación de nombre/estado/fechas y conflicto
  por tema inactivo. Se admitieron exactamente 5 MB y 2.000 preguntas (en uno
  o 2.000 temas); 5 MB más un byte y 2.001 preguntas se rechazaron sin guardar.
- Un trigger temporal falló en una opción después de guardar preguntas de
  otro tema: rollback completo, sin temas, preguntas ni opciones parciales.
  Respuesta 500 genérica con traceId, sin mensaje interno de la base.
- Chromium `import.spec.ts`: OK. Archivos con extensión/tamaño/JSON inválidos,
  previsualización, errores reales de validación y conflicto con rutas,
  estado Importando, resumen de creación/reutilización, repetición acumulativa
  y comprobación de las opciones/correcta en la pregunta importada.
- Regresiones `-Questions`, `-Topics` y `-Authentication`: OK; copias y
  restauraciones verificadas, bases y servidores temporales eliminados.
- Sintaxis PHP, núcleo HTTP y `git diff --check`: OK.
- Frontend: lint, 54 pruebas en 14 archivos y build: OK.

La demora del envío se simula solo para verificar el estado del formulario;
las importaciones y sus errores se procesan por PHP/MariaDB reales. Se
conservan pendientes la verificación de hosting y P09–P14.

## Cierre de P09 — 2026-10-11

Generación completada en PHP 8.3 y MariaDB 10.4.32 de XAMPP. Endpoint
autenticado con CSRF; 1–200 preguntas activas, selección sin repetidos y
barajado Fisher–Yates con `random_int()`. La ruta no consulta respuestas
correctas ni guarda intentos. El token AES-256-GCM protege usuario, IDs, orden
y caducidad de doce horas. OpenSSL y APP_KEY privado son obligatorios.

- `RunDatabaseTest.ps1 -Generation`: OK para cantidades mínima/máxima,
  varios temas, exclusión de preguntas/temas inactivos y temas ajenos,
  IDs/cantidades estrictos, 400/401/403/404, insuficiencia 409 con conteos
  incluso cero, preguntas y opciones sin repetidos y barajadas.
- Tokens reales de ambas sesiones verificados: exactamente los mismos IDs
  y orden de la respuesta, sin `isCorrect` ni `position`, sin crear intentos.
- `composer test:tokens`: OK para opacidad, nonce aleatorio, clave incorrecta
  o insuficiente, alteración de nonce/tag/ciphertext, versiones/claims
  inválidos, truncado/formato inválido, dos cuentas y caducidad exacta.
  Reloj controlado sin esperas y verificador independiente de la emisión.
- Chromium `generation.spec.ts`: OK para selección, validación, insuficiencia
  real tras desactivar una pregunta, carga, opciones en el orden recibido,
  respuesta conservada al navegar, dejar sin contestar, cancelar/confirmar
  abandono y recargar perdiendo el test. Almacenamiento del navegador vacío.
  Solo se demora la petición para verificar Preparando; respuestas reales.
- Regresiones `-Import`, `-Questions`, `-Topics`, `-Authentication`: OK.
  Copias/restauraciones y limpieza de todas las bases/servidores temporales: OK.
- Composer validado, sintaxis PHP y núcleo HTTP: OK; frontend lint, 54 pruebas
  en 14 archivos y build: OK. Vitest requirió ejecución fuera del sandbox
  tras un bloqueo de creación de procesos EPERM; ejecución completa aprobada.
- Esquema y frontend de producto conservados; sin cuentas o claves definitivas.

P10 es la siguiente tarea: entrega/corrección y snapshots transaccionales.
El aislamiento y caducidad ya se validan en el protector; su conexión HTTP con
la entrega pertenece a P10. Hosting y verificación integral siguen pendientes.

## Cierre de P10 — 2026-10-11

Entrega/corrección completadas en PHP 8.3 y MariaDB 10.4.32 de XAMPP, con
sesión y CSRF. La fórmula exacta usa aritmética racional entera, sin redondeos
intermedios, y persiste una única nota en millonésimas con regla 1. Se conserva
el esquema. React termina en el detalle desde snapshots; el listado queda en P11.

- `composer test:scoring`: OK para todos los contadores válidos de 1–200
  preguntas, blancos, extremos, negativos, decimales periódicos y mitades
  positivas/negativas; error de almacenamiento máximo media millonésima.
- `composer test:tokens` y validación Composer: OK.
- `RunDatabaseTest.ps1 -Completion`: OK. Validación de UUID/JSON/tipos,
  respuestas exactas, opciones propias, token manipulado/ajeno/caducado,
  contenido inexistente/inconsistente y estados 400/401/403/409/410/415.
  Intento mixto, extremos y 200 preguntas; contadores, nota entera y versión
  de regla comprobados directamente en SQL.
- Snapshots completos y en el orden generado; resultado por pregunta,
  correcta y selección comprobados. Repetición sin escrituras, incluso con
  answers reordenados o UUID en mayúsculas. Conflictos con otro test,
  respuestas diferentes o UUID de otra cuenta: 409 sin modificar histórico.
- Dos procesos PHP compiten realmente por el mismo UUID en MariaDB:
  uno crea, otro recupera el mismo resultado, con un único conjunto de
  snapshots. La prueba usa una barrera y demora SQL temporal.
- Un trigger falla en la segunda pregunta tras guardar la primera:
  rollback del intento/temas/preguntas/opciones, 500 genérico sin detalles
  privados y reintento posterior correcto.
- HTTP verifica detalle propio, 404 de intento ajeno y vigencia del test
  tras desactivar. Editar/desactivar contenido no cambia un intento entregado
  ni vuelve a puntuarlo en el reintento.
- Chromium `completion.spec.ts`: OK para selección, navegación, cancelación
  de abandono/entrega, blancos, foco/Escape, envío bloqueado mientras espera,
  resultado 2.50 y revisión de acierto/fallo/blanco.
  PHP guarda la primera entrega y el navegador pierde la respuesta:
  React reenvía exactamente UUID/token/respuestas, recibe 200 y abre el
  mismo intento. Edición/desactivación posterior y recarga conservan revisión.
- Regresiones `-Generation`, `-Questions`, `-Authentication`: OK.
  Copias/restauraciones y limpieza de bases/servidores temporales: OK.
- Sintaxis PHP, núcleo HTTP, lint, 54 pruebas React en 14 archivos y build: OK.
  Sin cambios de producto en React, cuentas definitivas ni commits.

P11 sigue pendiente para el listado/paginación del histórico y sus recorridos
de vacío, errores y navegación. El detalle necesario para P10 ya está
implementado y probado. Estadísticas, hosting y cierre integral pendientes.
