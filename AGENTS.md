# Instrucciones de OpoMatic-2000

## Alcance

Este repositorio migra OpoMatic-3000 a PHP/MySQL para un hosting con Apache y
phpMyAdmin. No utilizar Docker. No modificar `../opomatic-3000` desde tareas de
este repositorio. El frontend React se mantiene salvo cambios exigidos por el
contrato o la publicación.

## Fuentes de verdad

1. Petición actual del usuario.
2. `docs/requisitos.txt` y `docs/diseno-api.txt` para comportamiento y contrato.
3. `docs/diseno-base-datos.txt` y `database/schema.sql` para persistencia.
4. `docs/arquitectura.txt` y `docs/plan-implementacion.txt` para estructura.
5. `docs/tareas/README.md` para el estado de la migración.

La documentación describe el comportamiento objetivo. No marques tareas como
completadas sin código y comprobaciones correspondientes.

## Implementación

- Mantén la separación entre `Http`, `Application`, `Domain` e
  `Infrastructure`. Los contratos JSON no son filas de MySQL.
- Usa PDO con consultas preparadas, transacciones para importaciones e intentos,
  y `password_hash()`/`password_verify()` para las cuentas.
- No guardes secretos en Git ni coloques código privado bajo el directorio
  público del hosting.
- Valida toda entrada en PHP. Conserva rutas, códigos HTTP y Problem Details
  esperados por React; actualiza el contrato al cambiarlo.
- Comprueba `git status --short` antes y después de editar. Conserva cambios
  del usuario y no hagas commits sin petición explícita.
- Ejecuta pruebas proporcionales a cada cambio. Documenta las comprobaciones
  reales y los puntos que sigan pendientes.

En PowerShell usa `npm.cmd` para evitar el bloqueo de `npm.ps1`.
