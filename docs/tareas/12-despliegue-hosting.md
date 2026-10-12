# 12. Despliegue PHP

Estado: completado el 12 de octubre de 2026. No se utiliza Docker.

## T110 - Publicación

React se compiló con `VITE_API_BASE_URL=/`. El servidor sirve `frontend/dist`,
envía `/api/*` y `/health` a PHP y devuelve `index.html` al recargar rutas
internas. El código privado, los secretos y las copias están fuera del
directorio público.

**Comprobación:** HTTPS y login funcionan; `/health` devolvió 200,
`/api/auth/me` sin sesión devolvió 401, una ruta SPA interna cargó y el acceso
a un archivo privado devolvió 403. Las pantallas principales cargaron con
sesión.

## T111 - Operación

El esquema se importó en el servidor y se probaron 9 tablas, 10 claves
foráneas y 10 restricciones `CHECK`. Las dos cuentas iniciales se crearon
desde prompts ocultos. Una copia SQL privada se restauró en otra base temporal:
9 tablas y 2 cuentas recuperadas. La renovación del certificado se simuló
con éxito.

Las instrucciones, parámetros y archivos de configuración del servidor se
guardan solo en una carpeta local ignorada por Git. Queda como tarea operativa
conservar una copia cifrada fuera del servidor. P14 auditará el conjunto.
