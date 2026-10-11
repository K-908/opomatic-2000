# 03. Gestión de temas

Estado: pendiente.

## T020 - API de temas

- Implementar lista, alta, edición, desactivación y reactivación según
  `../diseno-api.txt`.
- Normalizar nombre, impedir duplicados y distinguir 400, 404 y conflicto.
- No eliminar físicamente temas usados por preguntas o histórico.

**Comprobación:** rutas, validaciones, unicidad y exclusión de temas inactivos
probadas con MySQL.

## T021 - Integración React

- Ejecutar la pantalla ya copiada contra la API PHP.
- Verificar estados de carga, vacío, error, teclado y recorrido feliz.

**Comprobación:** crear, editar y cambiar estado desde el navegador.
