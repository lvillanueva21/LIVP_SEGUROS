# Control visual desde LUIGISISTEMAS

Este cliente usa LUIGISISTEMAS como fuente principal de configuracion visual.
LIVP_SEGUROS conserva sus assets locales por defecto y solo usa la configuracion remota cuando esta disponible.

## Reglas del cliente

- No tocar `pizzarra/`: es un proyecto independiente ya cerrado.
- No eliminar `dist/` ni `plugins/`: forman parte del despliegue normal de los sistemas cliente.
- No reemplazar `includes/config_cliente.php` por una plantilla sin revisar: las claves de prueba y datos actuales se mantienen para despliegues rapidos.
- La configuracion visual remota debe tener fallback local para que el cliente siga funcionando si LUIGISISTEMAS no responde.
- Las imagenes remotas se pueden sincronizar localmente en `storage/visual_assets` para mejorar carga y reducir dependencia directa del servidor principal.

## Flujo visual esperado

1. El cliente pide la configuracion visual a LUIGISISTEMAS usando `API_CONFIG_VISUAL_ENDPOINT`.
2. La respuesta se guarda en cache JSON dentro de `storage/cache` como respaldo.
3. En cada carga visual, el cliente pregunta primero al servidor principal; si no responde, usa la cache local.
4. Si la respuesta trae un `asset_version`, el cliente lo usa para saber si debe actualizar imagenes locales.
5. Las imagenes remotas validas se descargan a `storage/visual_assets`.
6. Las pantallas usan la copia local cuando existe; si falla la descarga, usan la URL remota; si eso tampoco existe, usan los defaults locales.

## Campos controlados

- favicon
- logo
- fondo de login
- carrusel de login
- portada de sidebar
- avatar por defecto
- colores principales
- colores de header/sidebar/login
- titulo de login
- titulo del sistema cliente
- texto y version del footer
- botones superiores del login

## Sin base de datos local obligatoria

Para la configuracion visual no se requiere una base de datos en LIVP_SEGUROS.
La base de datos principal de LUIGISISTEMAS sigue siendo la fuente de verdad.
El cliente solo usa archivos de cache y assets locales para rendimiento y resiliencia.
