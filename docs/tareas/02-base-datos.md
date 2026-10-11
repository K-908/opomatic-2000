# 02. Base de datos MySQL

Estado: completado en MySQL 8.0.46 local. La prueba del hosting elegido sigue
pendiente en P00/P13.

## T010 - Conectar mediante PDO

- Leer credenciales de configuración no versionada.
- Usar `utf8mb4`, errores por excepción y consultas preparadas.
- Encapsular transacciones necesarias para importación y entrega.

**Comprobación realizada:** `composer test:db` en una base temporal: conexión
`utf8mb4`, preparación nativa, consultas parametrizadas y error de conexión sin
contraseña ni detalles del driver. `TransactionManager` confirmó una operación
y revirtió otra al propagarse una excepción.

## T011 - Validar esquema inicial

- Importar `../../database/schema.sql` en una base vacía; repetir en la versión
  real del hosting durante P00/P13 y documentar diferencias si aparecen.
- Probar unicidad, claves externas, CHECK y rollback.

**Comprobación realizada en local:** `RunDatabaseTest.ps1` creó dos bases
temporales en MySQL 8.0.46. Importó las nueve tablas, comprobó 10 claves
foráneas y 10 CHECK, rechazo de duplicados/filas huérfanas/valores inválidos,
commit y rollback. Exportó una copia SQL, la restauró en la segunda base y
verificó las nueve tablas. Eliminó ambas bases tras la prueba.

La prueba usa `opomatic_p03_*` y exige una base vacía para no alterar datos
reales. La compatibilidad del proveedor y su restauración se verificarán
cuando se elija hosting; no se atribuye al servidor de producción la prueba
local.
