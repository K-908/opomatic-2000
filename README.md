# OpoMatic-2000

Port de OpoMatic-3000 para hosting convencional con **PHP y MySQL**, sin Docker.
El repositorio original `opomatic-3000` permanece independiente. Se parte de
una base de datos vacía: no hay migración de datos de SQLite ni de SQL Server.

## Estado

El frontend React está copiado y PHP ya dispone de enrutamiento HTTP,
`GET /health` y errores Problem Details. **Los endpoints funcionales siguen
pendientes**, por lo que la aplicación completa no puede
usarse ni publicarse todavía. Los documentos describen el contrato
y el trabajo pendiente; el historial de tareas cerradas de OpoMatic-3000 no se
presenta aquí como trabajo ya realizado.

## Estructura

```text
backend/
  public/            Punto de entrada web y router local de PHP
  src/Http/          Salud; otras rutas y respuestas (pendientes)
  src/Application/   Casos de uso (pendiente)
  src/Domain/        Reglas y modelos puros (pendiente)
  src/Infrastructure/ PDO, MySQL y servicios externos (pendiente)
  tests/Unit/        Pruebas de reglas (pendiente)
  tests/Integration/ Pruebas de API y MySQL (pendiente)
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
8.0.46. No contiene cuentas ni contraseñas; el alta de las dos cuentas deberá
hacerse desde PHP con `password_hash()` cuando exista el backend.

Las credenciales de MySQL irán en configuración local fuera del control de
versiones. Usa [`.env.example`](.env.example) solo como lista de variables.

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
