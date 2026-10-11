# 02. Base de datos MySQL

Estado: pendiente.

## T010 - Conectar mediante PDO

- Leer credenciales de configuración no versionada.
- Usar `utf8mb4`, errores por excepción y consultas preparadas.
- Encapsular transacciones necesarias para importación y entrega.

**Comprobación:** conexión aislada de prueba y error seguro sin filtrar secretos.

## T011 - Validar esquema inicial

- Importar `../../database/schema.sql` en una base vacía de la versión real del
  hosting; documentar diferencias de MySQL/MariaDB si aparecen.
- Probar unicidad, claves externas, CHECK y rollback.

**Comprobación:** nueve tablas, restricciones reales y restauración desde copia.

El esquema ya se importó y probó en MySQL 8.0.46 local, pero falta la prueba
contra el servidor de producción.
