# Pruebas manuales Catalogos V1

## Aseguradoras y logos

1. Crear aseguradora sin logo.
2. Crear aseguradora con logo valido PNG, JPEG o WEBP.
3. Confirmar vista previa local antes de guardar.
4. Confirmar barra de progreso durante subida.
5. Reemplazar logo y confirmar que el archivo anterior se elimina solo tras exito.
6. Quitar logo y confirmar fallback visual.
7. Confirmar logo en listado.
8. Intentar subir SVG, GIF, PDF o archivo renombrado.
9. Intentar subir archivo con MIME falso.
10. Probar archivo que exceda limites reales del servidor.
11. Probar carpeta sin permisos de escritura.
12. Probar endpoint de logo sin sesion.
13. Probar usuario sin `puede_ver`.
14. Probar CSRF invalido en cambios.

## Catalogos existentes

1. Crear, editar, Activar y Desactivar aseguradora.
2. Crear, editar, Activar y Desactivar ramo.
3. Crear, editar, Activar y Desactivar producto o plan.
4. Confirmar que aseguradora con productos activos no se desactiva.
5. Confirmar que ramo con productos activos no se desactiva.
6. Confirmar que producto no se crea, edita ni reactiva con aseguradora o ramo desactivado.
7. Confirmar filtros, busqueda con debounce, boton Buscar, Enter, Limpiar y paginacion.
8. Confirmar estados vacios.
9. Confirmar que los botones dependen de permisos.
10. Confirmar auditoria con usuario externo y hora Lima.

## Seguridad estatica

- No queda codigo activo de BLOB, Base64 ni contenido binario para logos.
- No se devuelve imagen por JSON.
- No hay almacenamiento fisico persistente fuera de `almacen/aseguradoras/logos/` para nuevas cargas.
- No existen rutas de archivo controladas por usuario.
- No se usa nombre original como ruta fisica.
- Solo existe un logo vigente por aseguradora.
- Un error de BD no deja archivo nuevo huerfano.
- La eliminacion de logo no borra la aseguradora.
- La terminologia visible usa Activar, Desactivar, Activo y Desactivado.
