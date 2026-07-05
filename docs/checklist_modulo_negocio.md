# Checklist de modulo de negocio

Este checklist debe revisarse antes de marcar un modulo de negocio de LIVP_SEGUROS como listo.

## Maestro y permisos

- [ ] Pagina logica creada en LIVP_LSISTEMAS.
- [ ] `codigo_pagina` coincide con `LIVP_SEGUROS/modules/{codigo_pagina}/index.php`.
- [ ] Rol autorizado tiene `puede_ver`.
- [ ] Permisos de accion asignados cuando aplica: `puede_crear`, `puede_editar`, `puede_eliminar`.
- [ ] Usuario sin permiso no ve el modulo en el menu.
- [ ] Usuario sin permiso no puede entrar por URL directa.

## Estructura local

- [ ] Carpeta fisica del modulo creada.
- [ ] `modules/{codigo}/index.php` existe.
- [ ] Acceso directo al archivo del modulo bloqueado o controlado.
- [ ] El modulo incluye `includes/module_guard.php`.
- [ ] El modulo llama `cb_require_module_context('{codigo}')`.
- [ ] El modulo se carga desde `modulo.php?m={codigo}`.
- [ ] Si se uso `modules/_plantilla/`, se copio como `modules/{codigo}` y se reemplazo `_plantilla` por el codigo real.
- [ ] Endpoints locales creados solo si son necesarios.
- [ ] Endpoints privados incluyen `includes/session_guard.php`.

## Seguridad

- [ ] Sesion local validada.
- [ ] Permiso especifico validado por accion.
- [ ] Metodo HTTP validado.
- [ ] CSRF probado en operaciones que cambian datos cuando corresponda.
- [ ] Entrada validada y normalizada en backend.
- [ ] PDO y prepared statements usados.
- [ ] No hay SQL crudo concatenado con datos de usuario.
- [ ] No se exponen secretos en HTML, JS, JSON, logs ni errores.
- [ ] No se exponen rutas fisicas del servidor.

## Base de datos local

- [ ] SQL entregado por chat para ejecucion manual en phpMyAdmin.
- [ ] No se ejecuto SQL automatico.
- [ ] No se creo archivo `.sql`.
- [ ] Tabla documentada en `docs/tablas_livp_seguros.md`.
- [ ] Indices y unique revisados.
- [ ] Foreign keys revisadas cuando aplica.
- [ ] Auditoria definida cuando aplica.
- [ ] Hora de auditoria validada con PHP `America/Lima` y sesion PDO `-05:00`.
- [ ] Regla de estado/desactivacion definida.

## UX y frontend

- [ ] Mantiene AdminLTE 3 + Bootstrap 4.
- [ ] Mantiene rutas relativas.
- [ ] Estado vacio implementado.
- [ ] Loading implementado.
- [ ] Modales usados para crear/editar cuando aplica.
- [ ] Toasts o notificaciones coherentes.
- [ ] No usa `alert()`, `confirm()` ni `prompt()`.
- [ ] Tabla con paginacion de 10 cuando aplica.
- [ ] Botones de acciones icon-only, verticales, con `title` y `aria-label`.
- [ ] Textos largos truncados con criterio y valor completo disponible.
- [ ] Responsive probado.

## Pruebas manuales

- [ ] Prueba con usuario autorizado.
- [ ] Prueba con usuario sin permiso.
- [ ] Prueba con URL directa a modulo no permitido.
- [ ] Prueba con sesion expirada.
- [ ] Prueba de listado vacio.
- [ ] Prueba de validaciones frontend.
- [ ] Prueba de validaciones backend.
- [ ] Prueba de error de servidor controlado.
- [ ] Prueba de trazabilidad cuando aplica.

## Restricciones de repositorio

- [ ] No se modifico `pizzarra/` sin justificacion.
- [ ] No se modifico `dist/` sin justificacion.
- [ ] No se modifico `plugins/` sin justificacion.
- [ ] No se modifico `includes/config_cliente.php` sin justificacion.
- [ ] No se copiaron usuarios, roles internos, permisos internos ni paginas internas del maestro.

## Referencia de Catalogos V1

Para el primer modulo real `catalogos`, revisar adicionalmente:

- `docs/diseno_catalogos_v1.md`
- `docs/referencia_demo_catalogos.md`
- `modules/catalogos/index.php`
- `api/catalogos/`

Validaciones especificas:

- [ ] Aseguradora no se desactiva si tiene productos activos.
- [ ] Ramo no se desactiva si tiene productos activos.
- [ ] Producto no se crea, edita ni reactiva con aseguradora o ramo desactivado.
- [ ] No existe accion de borrado fisico.

Validaciones de logos:

- [ ] Crear aseguradora sin logo.
- [ ] Crear aseguradora con PNG.
- [ ] Subir JPEG y WEBP validos.
- [ ] Vista previa local antes de guardar.
- [ ] Barra de progreso durante subida.
- [ ] Reemplazar logo y confirmar cache bust.
- [ ] Quitar logo y confirmar fallback visual.
- [ ] Validar formato invalido, MIME falso, archivo mayor de 2 MiB y dimensiones fuera de rango.
- [ ] Confirmar que la imagen no viaja por JSON.
- [ ] Confirmar que la imagen se guarda fisicamente y la BD solo guarda metadatos.
