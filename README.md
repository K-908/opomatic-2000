# OpoMatic-2000

Port de OpoMatic-3000 para hosting convencional con **PHP y MySQL**, sin Docker.
El repositorio original `opomatic-3000` permanece independiente. Se parte de
una base de datos vacía: no hay migración de datos de SQLite ni de SQL Server.

## Estado

El frontend React está copiado y PHP ya dispone de enrutamiento HTTP,
`GET /health`, errores Problem Details, acceso PDO a MySQL, autenticación, gestión de temas/preguntas, importación JSON, generación, entrega/corrección de tests y detalle del intento.
**El listado del histórico y las estadísticas siguen pendientes**,
por lo que la aplicación completa no puede
usarse ni publicarse todavía. Los documentos describen el contrato
y el trabajo pendiente; el historial de tareas cerradas de OpoMatic-3000 no se
presenta aquí como trabajo ya realizado.

## Estructura

```text
backend/
  public/            Punto de entrada web y router local de PHP
  src/Http/          Salud, cuentas, banco, tests y detalle de intento
  src/Application/   Cuentas, banco, tests y snapshots
  src/Domain/        Validación, puntuación y errores del banco/tests
  src/Infrastructure/ PDO, sesiones, repositorios y transacciones
  tests/Unit/        Pruebas de protección/caducidad de tokens
  tests/Integration/ Pruebas del núcleo HTTP y de MySQL
database/schema.sql  Esquema inicial importable con phpMyAdmin
deploy/apache/       Configuración de publicación (pendiente)
frontend/            React + TypeScript + Vite, copiado de OpoMatic-3000
docs/                Especificación y plan de migración
```

Los directorios vacíos contienen `.gitkeep` para que Git los conserve. No se
han copiado los proyectos .NET, los archivos de Docker ni los secretos del
repositorio de origen.

## API PHP local

Desde la raíz de `opomatic-2000`, con PHP 8.2+ y Composer instalados:

```powershell
composer install
$env:APP_ENV = 'development'
composer serve
```

Abre `http://127.0.0.1:8000/health`. La respuesta actual es
`{"status":"ok","application":"OpoMatic-2000"}`. Las rutas inexistentes
devuelven 404 y un método incorrecto devuelve 405 con `Allow`, ambos como
`application/problem+json`. Ejecuta las pruebas de la capa HTTP con
`composer test:http`. El servidor integrado de
PHP sirve solo para desarrollo; `composer serve` no inicia MySQL ni el frontend.
Las variables de [`.env.example`](.env.example) se configuran en el proceso o
en el panel del hosting: copiar ese archivo a `.env` no las carga
automáticamente. En producción usa `APP_ENV=production`. No introduzcas
contraseñas en archivos versionados.

## Base de datos

En el panel del hosting, crea una base MySQL vacía y un usuario con permisos
sobre ella. En phpMyAdmin, selecciona esa base, abre **Importar** y elige
[`database/schema.sql`](database/schema.sql). El esquema se comprobó en MySQL
8.0.46. No contiene cuentas ni contraseñas; el alta de las dos cuentas se hace
mediante el comando PHP descrito abajo.

Las credenciales de MySQL se leen de `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`
y `DB_PASSWORD` en el proceso PHP. Usa [`.env.example`](.env.example) solo como
lista de variables; copiarla a `.env` no configura la aplicación. La conexión
usa `utf8mb4`, consultas preparadas nativas y transacciones para operaciones
compuestas. `composer install` comprueba que estén disponibles `pdo` y
`pdo_mysql`. La autenticación y el registro de accesos ya usan la base; los endpoints de tests, histórico y estadísticas siguen pendientes.

Para repetir la prueba de integración local en **dos bases temporales nuevas**,
con un usuario MySQL que pueda crearlas y borrarlas:

```powershell
$env:OPOMATIC_DB_ADMIN_PASSWORD = Read-Host 'Contraseña MySQL de prueba'
.\backend\tests\Integration\RunDatabaseTest.ps1 -AdminUser root
Remove-Item Env:OPOMATIC_DB_ADMIN_PASSWORD
```

El script crea bases con prefijo `opomatic_p03_`, importa el esquema, prueba
consultas, restricciones y transacciones, restaura una copia en la segunda base
y elimina ambas al terminar. No lo ejecutes contra una base con datos; la
prueba `composer test:db` exige ese prefijo y las nueve tablas vacías. La
compatibilidad del hosting se comprobará al elegir proveedor.

## Alta inicial de cuentas

Tras importar el esquema y configurar `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`
y `DB_PASSWORD` en el proceso, ejecuta **una vez** desde la raíz del proyecto:

```powershell
.\backend\bin\BootstrapUsers.ps1
```

El wrapper pide en pantalla, sin mostrarlas, las contraseñas de `Gogloglo` y
`Virginia`. Invoca `backend/bin/bootstrap-users.php` por PHP CLI y le entrega
las contraseñas por entrada estándar; no se pasan como argumentos ni se guardan
en archivos. Se admite entre 1 y 72 bytes por contraseña (límite del algoritmo
predeterminado de PHP 8.2); elige contraseñas largas y distintas. El proceso
rechaza una tabla `Users` que ya contenga cuentas y no modifica los hashes si
se repite. La inserción de las dos cuentas es atómica. El comando está fuera
de `backend/public/` y nunca debe publicarse dentro del document root.

En un hosting con acceso SSH se puede ejecutar el PHP CLI pasando dos líneas
por entrada estándar desde un prompt que oculte la entrada. Si el proveedor
solo ofrece panel web, habrá que resolver esta ejecución en P13 antes de abrir
el acceso al sitio. **P04 implementa y prueba el proceso; todavía no se han
creado cuentas definitivas.**

Para repetir la prueba de P04 con contraseñas aleatorias y bases desechables:

```powershell
$env:OPOMATIC_DB_ADMIN_PASSWORD = Read-Host 'Contraseña MySQL de prueba'
.\backend\tests\Integration\RunDatabaseTest.ps1 -AdminUser root -BootstrapAccounts
Remove-Item Env:OPOMATIC_DB_ADMIN_PASSWORD
```

## Login y sesiones

La API implementa `POST /api/auth/login`, `GET /api/auth/session`,
`POST /api/auth/logout` y `GET /api/auth/accesses`. La sesión dura como máximo
doce horas. Las peticiones que cambian estado envían `X-CSRF-Token`; React lo
obtiene de la cookie `OPOMATIC_CSRF` después del login. El sexto intento de
login desde una IP en un minuto devuelve 429. Los accesos muestran solo los
50 registros más recientes de la cuenta autenticada.

PHP necesita escribir en un directorio privado para sesiones y el límite de
login. Por defecto usa `backend/var/auth`, ignorado por Git y separado de
`backend/public`. Se puede cambiar con `AUTH_STORAGE_DIR`; debe quedar fuera
del document root. En producción, configura `APP_ENV=production` y HTTPS real:
el login se rechaza si PHP no detecta HTTPS. El servidor toma la IP de
`REMOTE_ADDR` y no confía en `X-Forwarded-For` sin un proxy configurado.

Para usar React en desarrollo, inicia `composer serve` y, en otra terminal,
`npm.cmd run dev` dentro de `frontend/`. Vite envía `/api` y `/health` al
servidor PHP local; navegador y API comparten origen en desarrollo. Si PHP usa
otro puerto, define `OPOMATIC_DEV_API_TARGET` antes de iniciar Vite.

La prueba HTTP de P05 usa cuentas y bases temporales:

```powershell
$env:OPOMATIC_DB_ADMIN_PASSWORD = Read-Host 'Contraseña MySQL de prueba'
.\backend\tests\Integration\RunDatabaseTest.ps1 -AdminUser root -Authentication
Remove-Item Env:OPOMATIC_DB_ADMIN_PASSWORD
```

## Gestión de temas (P06)

La API implementa lista, detalle, alta, edición y activación/desactivación en
`/api/topics`. Los nombres admiten entre 1 y 150 caracteres Unicode; se eliminan
espacios exteriores y la colación `utf8mb4_unicode_ci` impide duplicados sin
distinguir mayúsculas ni acentos, incluidos los temas inactivos. Desactivar
un tema conserva sus preguntas y su estado individual. React funciona contra
estas rutas con la sesión y el token CSRF existentes.

Con PHP 8.2+ (PDO MySQL y cURL), Composer, Node y MySQL/MariaDB disponibles en
PATH, prepara Chromium una vez con `npm.cmd run test:e2e:install` en `frontend/`.
Después define `OPOMATIC_DB_ADMIN_PASSWORD` en el proceso y ejecuta desde la raíz:

```powershell
.\backend\tests\Integration\RunDatabaseTest.ps1 -AdminUser root -Topics
```

El runner importa el esquema en bases temporales, genera contraseñas aleatorias,
prueba las rutas y ejecuta `topics.spec.ts` contra PHP y Vite. Incluye teclado,
validación, duplicados, confirmación, filtros y estados de carga, vacío y error.
Comprueba una copia/restauración y elimina las bases y servidores temporales.
Para repetir la regresión de autenticación usa `-Authentication` por separado.

## Gestión de preguntas (P07)

La API implementa lista paginada, detalle administrativo, alta, edición y estado
lógico en `/api/questions`. Admite filtros por tema, preguntas inactivas y texto,
con páginas de hasta 100 elementos. El detalle muestra las cuatro opciones y su
respuesta correcta; la lista no expone opciones. El banco es compartido entre
las dos cuentas autenticadas.

PHP valida enunciados de 1–10.000 caracteres, textos de opción de 1–1.000,
cuatro posiciones distintas y una única correcta. Alta y edición son atómicas;
al editar se conservan los IDs de las opciones y los snapshots históricos.
Guardar o reactivar exige un tema activo. El listado administrativo permite
mantener preguntas de temas inactivos; la generación de P09 las excluye. La importación JSON corresponde a P08.

Con el mismo entorno de pruebas y Chromium preparados para P06:

```powershell
.\backend\tests\Integration\RunDatabaseTest.ps1 -AdminUser root -Questions
```

Este modo prueba las rutas, rollback ante errores forzados y conservación de
snapshots con claves foráneas reales. Ejecuta `questions.spec.ts` contra PHP y
Vite, comprueba copia/restauración y elimina sus bases y servidores temporales.

## Importación JSON (P08)

`POST /api/questions/import` acepta el documento de
`frontend/public/examples/questions-import-example.json`, con temas, preguntas,
cuatro textos de opción y `correctOption` de 1 a 4. Requiere sesión y CSRF.
El formulario permite seleccionar un archivo, previsualizar cantidad de temas
y preguntas, confirmar y consultar el resumen de temas creados/reutilizados.

El servidor admite hasta 5 MB (5.242.880 bytes) y 2.000 preguntas. Valida todo
el documento; devuelve 400 con rutas precisas, 409 para temas inactivos y 413
si se excede el tamaño. Los nombres usan la misma colación que Temas, sin distinguir
mayúsculas ni acentos, y se eliminan espacios exteriores. Cada tema debe
contener preguntas; el límite de preguntas permite como máximo 2.000 temas.

Los temas activos se reutilizan sin cambiar nombre, estado ni fechas; los
nuevos se crean activos. Todo se guarda en una única transacción. Si falla
cualquier inserción, se revierte la importación completa. La importación es
acumulativa: repetir un archivo vuelve a insertar sus preguntas.

Con el entorno y Chromium preparados para las pruebas anteriores:

```powershell
.\backend\tests\Integration\RunDatabaseTest.ps1 -AdminUser root -Import
```

Este modo verifica límites exactos, rutas de validación, duplicados, conflictos,
reutilización y rollback forzado, y ejecuta `import.spec.ts` contra PHP y Vite.
Usa cuentas aleatorias y bases temporales, comprueba copia/restauración y
elimina sus bases y servidores al terminar.

## Generación de tests (P09)

`POST /api/tests/generate` acepta `topicIds` sin repetir y `numberOfQuestions`
de 1 a 200, con sesión y CSRF. Selecciona preguntas activas de temas activos,
sin repetición, y baraja preguntas y opciones en PHP con `random_int()`.
La respuesta conserva el contrato de React, sin `isCorrect`. Temas
inexistentes/inactivos devuelven 404; insuficiencia, 409 con los conteos
`requestedCount` y `availableCount`.

El token opaco AES-256-GCM conserva usuario, IDs y orden, con caducidad de
doce horas. Exige OpenSSL y `APP_KEY` privado de al menos 32 bytes aleatorios.
Para desarrollo, genera la clave **antes de arrancar PHP** y consérvala en
ese entorno mientras uses sus tokens:

```powershell
$env:APP_KEY = (& php -r 'echo bin2hex(random_bytes(32));')
composer serve
```

En hosting configura una clave estable fuera de Git y del directorio público;
cambiarla invalida todos los tokens anteriores. `.env.example` es una referencia
y no se carga automáticamente.

Con el entorno de pruebas anterior:

```powershell
composer test:tokens
.\backend\tests\Integration\RunDatabaseTest.ps1 -AdminUser root -Generation
```

El runner genera una clave temporal propia, comprueba HTTP y
`generation.spec.ts` en Chromium, restaura la configuración y elimina bases y
servidores de prueba. Verifica validación, conteos reales, navegación conservando
respuestas, abandono y pérdida del test al recargar. Generar no guarda un
intento ni progreso en MySQL, localStorage o sessionStorage. La entrega y
corrección están implementadas en P10.

## Entrega y corrección (P10)

`POST /api/tests/finish` acepta `submissionId` UUID, `testToken` y una entrada
por pregunta en `answers`, con `selectedOptionId` entero o `null` explícito.
Requiere sesión y CSRF. Verifica token, cuenta, caducidad y opciones propias;
corrige en servidor y guarda intento y snapshots en una sola transacción.
La nota aplica `(10/N)*(A-0,25*F)`, puede ser negativa y se guarda en
millonésimas sin redondeos intermedios. React la presenta con dos decimales.

La primera entrega devuelve 201 y la repetición compatible, 200 con el mismo
intento. Un UUID usado con datos diferentes devuelve 409; un token caducado,
410; un token ajeno o CSRF inválido, 403. Una carrera no duplica intentos y un
fallo en cualquier snapshot revierte la entrega completa.

React abre el resultado en `/attempts/{id}`. La API `GET /api/attempts/{id}`
lee solo snapshots de la cuenta autenticada y devuelve 404 para intentos
ajenos. La revisión conserva texto, opciones, orden, selección y correcta
aunque se edite/desactive después el banco. Al corregir usa el contenido actual
del banco; un token vigente puede entregarse tras una desactivación lógica.
Listado y paginación del histórico corresponden a P11.

Con el entorno PHP 8.2+, OpenSSL, PDO MySQL, cURL y Chromium anterior:

```powershell
composer test:scoring
composer test:tokens
.\backend\tests\Integration\RunDatabaseTest.ps1 -AdminUser root -Completion
```

El modo `-Completion` prueba HTTP, dos entregas simultáneas con procesos PHP,
rollback forzado y el recorrido `completion.spec.ts` desde nuevo test hasta
la revisión. Simula perder la respuesta después de guardarla en PHP y verifica
que React reintenta sin duplicar el intento. Usa cuentas/clave/bases temporales,
comprueba copia/restauración y elimina sus recursos al finalizar.

## Frontend

El frontend es **React**, no Angular. Para verificarlo localmente:

```powershell
cd frontend
npm.cmd ci
npm.cmd run lint
npm.cmd run test:run
npm.cmd run build
```

Para compilarlo en el mismo origen que una futura API PHP:

```powershell
$env:VITE_API_BASE_URL = '/'
npm.cmd run build
```

El contenido de `frontend/dist/` se publicará en el directorio web del hosting.
Apache necesitará enviar `/api/*` a PHP y las rutas de la SPA a `index.html`.
La configuración concreta se preparará con la API y se verificará en el
hosting elegido. Compilar React no sustituye el backend.

Los recorridos Playwright de banco, generación y entrega hasta la revisión
ya se verifican contra PHP en P06–P10. El recorrido integral copiado sigue
pendiente hasta completar el listado del histórico y las estadísticas.
`npm.cmd run test:e2e` exige una instalación local de prueba y las variables
`OPOMATIC_E2E_BASE_URL`, `OPOMATIC_E2E_GOGLOGLO_PASSWORD` y
`OPOMATIC_E2E_VIRGINIA_PASSWORD`; no arranca ni modifica el repositorio .NET.

## Documentación

- [Requisitos](docs/requisitos.txt) y [funcionalidades](docs/funcionalidades.txt)
  describen el producto.
- [Contrato de la API](docs/diseno-api.txt) fija lo que espera React.
- [Base de datos](docs/diseno-base-datos.txt) y [arquitectura](docs/arquitectura.txt)
  describen el port PHP/MySQL.
- [Lista de tareas de la migración](docs/plan-migracion-php.md),
  [plan por etapas](docs/plan-implementacion.txt) y
  [tareas por funcionalidad](docs/tareas/README.md) ordenan el trabajo pendiente.
- [Entorno y requisitos del hosting](docs/entorno-hosting.md) recoge la
  comprobación local y lo que habrá que confirmar en el proveedor.
- [Auditoría](docs/auditoria-mvp.md) distingue la evidencia heredada de las
  verificaciones necesarias en PHP.
- [Backlog posterior](docs/backlog-posterior-mvp.md) conserva ideas futuras.
