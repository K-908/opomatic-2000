# Tareas de migración de OpoMatic-2000

Estas tareas describen trabajo **pendiente** en PHP/MySQL. Conservan las áreas
funcionales del repositorio de origen, pero ninguna tarea de .NET se considera
automáticamente completada aquí. La estructura y documentación iniciales se
prepararon antes de comenzar los bloques de implementación. P00–P13 están
completadas; P14, la auditoría integral, sigue pendiente.

El orden concreto de trabajo, dependencias y comprobaciones transversales se
resume en [`../plan-migracion-php.md`](../plan-migracion-php.md).

| Orden | Bloque | Archivo | Estado |
|---:|---|---|---|
| 0 | Entorno y hosting | [00-entorno-hosting.md](00-entorno-hosting.md) | Completado; datos del servidor guardados solo en local |
| 1 | Base técnica y API | [01-fundamentos.md](01-fundamentos.md) | Completado (2/2) |
| 2 | MySQL y acceso PDO | [02-base-datos.md](02-base-datos.md) | Completado en local y servidor elegido |
| 3 | Cuentas y sesiones | [11-autenticacion.md](11-autenticacion.md) | Completado localmente; cuentas y login HTTPS verificados en el servidor |
| 4 | Temas | [03-temas.md](03-temas.md) | Completado localmente (2/2) |
| 5 | Preguntas | [04-preguntas.md](04-preguntas.md) | Completado localmente (2/2) |
| 6 | Importación JSON | [05-importacion-json.md](05-importacion-json.md) | Completado localmente (2/2) |
| 7 | Generación de tests | [06-generacion-tests.md](06-generacion-tests.md) | Completado localmente (2/2) |
| 8 | Entrega y corrección | [07-realizacion-tests.md](07-realizacion-tests.md) | Completado localmente (3/3) |
| 9 | Histórico | [08-historico.md](08-historico.md) | Completado localmente (2/2) |
| 10 | Estadísticas | [09-estadisticas.md](09-estadisticas.md) | Completado localmente (2/2) |
| 11 | Hosting sin Docker | [12-despliegue-hosting.md](12-despliegue-hosting.md) | Completado con HTTPS |
| 12 | Cierre del MVP PHP | [10-cierre-mvp.md](10-cierre-mvp.md) | Pendiente |

El orden numérico de los archivos conserva las áreas de la versión anterior;
el orden de la tabla refleja dependencias. Los criterios
detallados están en cada archivo. Al terminar una tarea, registra prueba real
y actualiza su estado. No copies fechas ni resultados de OpoMatic-3000.
