# 12. Despliegue en hosting compartido

Estado: pendiente. No se utilizará Docker.

## T110 - Publicación Apache/PHP

- Definir ubicación de `backend/public` y `frontend/dist` según document root.
- Enrutar `/api/*` y `/health` a PHP y el resto de rutas SPA a `index.html`.
- Mantener código privado, `.env` y copias fuera del directorio público.
- Usar `VITE_API_BASE_URL=/` al compilar React.

**Comprobación:** login y todas las rutas funcionan con HTTPS, incluso tras
recargar una URL interna; no se puede descargar código ni configuración privada.

## T111 - Operación

- Documentar importación de esquema en phpMyAdmin, alta de cuentas, actualización
  de SQL, copias y restauración.
- Comprobar requisitos concretos de PHP, MySQL/MariaDB, extensiones y Apache
  del proveedor elegido.

**Comprobación:** publicación y restauración reproducibles sin contenedores.
