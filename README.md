# OpoMatic-2000

Port de OpoMatic-3000 para hosting convencional con **PHP y MySQL**, sin Docker.
El repositorio original `opomatic-3000` permanece independiente. Se parte de
una base de datos vacía: no hay migración de datos de SQLite ni de SQL Server.

## Estado

El frontend React está copiado y PHP ya dispone de enrutamiento HTTP,
`GET /health`, errores Problem Details, acceso PDO a MySQL y autenticación.
**Los endpoints de temas, preguntas, tests y estadísticas siguen pendientes**,
por lo que la aplicación completa no puede
usarse ni publicarse todavía. Los documentos describen el contrato
y el trabajo pendiente; el historial de tareas cerradas de OpoMatic-3000 no se
presenta aquí como trabajo ya realizado.

## Estructura

```text
backend/
  public/            Punto de entrada web y router local de PHP
  src/Http/          Salud, autenticación y respuestas; otras rutas pendientes
  src/Application/   Casos de uso (pendiente)
  src/Domain/        Reglas y modelos puros (pendiente)
  src/Infrastructure/ PDO, sesiones, repositorios y transacciones
  tests/Unit/        Pruebas de reglas (pendiente)
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
`pdo_mysql`. La API todavía no usa la base porque faltan los endpoints.

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

Los recorridos Playwright copiados quedan pendientes hasta que exista PHP.
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
