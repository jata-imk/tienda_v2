# Auditoría

La API registra acciones de negocio y seguridad en MariaDB mediante dos tablas:
`audit_events` (una acción) y `audit_changes` (sus registros afectados). Una recepción
de pedido conserva en un mismo evento sus cambios en pedido, líneas, variantes,
movimientos e historial de estados. El Kardex y el historial de pedidos mantienen
su función actual.

Guía de pantallas, campos y consumo para frontend:
[entrega-auditoria-frontend.md](entrega-auditoria-frontend.md).

## Activación y despliegue

1. Aplicar `php artisan migrate --force` antes de atender peticiones con esta versión.
2. Elegir los módulos con `AUDIT_MODULES` y reconstruir la configuración si el entorno
   usa caché: `php artisan config:cache`.
3. Reiniciar los procesos persistentes y mantener un worker de colas para la limpieza
   de imágenes: `php artisan queue:work --tries=5`.
4. Comprobar un cambio de prueba y su consulta con un administrador. Registrar en la
   entrega la fecha UTC de activación de cada etapa; el primer evento de cada módulo
   permite comprobar el inicio efectivo de captura.
5. Opcional, una sola vez: importar el historial previo (ver
   [Historial previo a la auditoría](#historial-previo-a-la-auditoría)).

Por defecto se habilita toda la cobertura implementada:

```dotenv
AUDIT_MODULES=security,users,inventory,orders,products,catalogs,configuration,documents
```

Para desplegar por etapas, usar listas acumulativas:

| Etapa | Agregar módulos | Cobertura |
|---|---|---|
| Base + 1 | `security,users` | Autenticación, rechazos, usuarios, rol asignado, contraseñas y sesiones |
| 2 | `inventory,orders,documents` | Movimientos, existencias, pedidos, líneas, estados, recepciones y PDF |
| 3 | `products,catalogs,configuration` | Productos, variantes, relaciones, claves/SKU, imágenes, catálogos, empresa y monedas |

Un módulo deshabilitado opera sin captura de auditoría de negocio. Esta configuración
es de despliegue y no se acepta desde una petición. No deshabilitar módulos para
sortear un fallo de almacenamiento. No existe un interruptor de limpieza automática.

## Matriz de acciones

El catálogo ejecutable está en `App\Support\AuditActions`. Cada handler de las 78
rutas actuales (74 originales y 4 de consulta de auditoría) se clasifica de forma
explícita. Una prueba exige clasificar también las rutas nuevas.

| Área | Acciones persistidas |
|---|---|
| Seguridad | `auth.login`, `auth.logout`, `auth.sessions_revoked`, `security.unauthenticated`, `security.access_denied` |
| Usuarios | `users.created`, `users.updated`, `users.deactivated` |
| Inventario | `inventory.movements_registered`, además de los movimientos iniciales y de compra asociados a sus acciones de origen |
| Pedidos | `orders.created`, `orders.updated`, `orders.confirmed`, `orders.cancelled`, `orders.received` |
| PDF | `orders.pdf_generated`, tanto inline como attachment |
| Productos | `products.created`, `products.updated`, `products.deactivated`, `products.image_changed`, `products.image_deleted` |
| Variantes e imágenes | `variants.created/updated/deactivated`, `product_images.created/deleted` |
| Catálogos | `categories`, `size_groups`, `sizes`, `colors`: `created/updated/deactivated` |
| Configuración | `company_info.created/updated`, `currencies.created/updated/deactivated` |

Los catálogos de roles (`user_types`) no tienen endpoints de administración nuevos.
Sus cambios y revocaciones asociadas se capturan cuando un proceso utiliza la unidad
de auditoría. La asignación de rol a un usuario se captura en `users.updated`.

Las consultas `index`, `show`, `query`, dashboard, catálogos, historial, siguiente clave
y los `dryRun` de renombrado generan únicamente registro técnico. Un parámetro
`dryRun` en otro endpoint no deshabilita la auditoría. Los rechazos 401/403 se auditan
también para rutas de consulta. Consultar la propia bitácora no crea eventos recursivos.

## Contenido y garantías

El evento conserva acción, módulo, resultado, fecha UTC con microsegundos,
identificador de petición, origen (`api`, `system` o `legacy`), usuario, nombre de usuario,
código/nombre del rol históricos, entidad principal, ruta sin query string, método,
IP y agente del cliente. El ID de usuario y los sujetos son referencias históricas:
eliminar o renombrar una entidad no elimina su auditoría.

`X-Request-Id` se genera en el servidor y aparece en la respuesta HTTP, la bitácora
y los logs técnicos. No se toma el actor de `idUser`, otros campos del cliente ni
claims de JWT sin validar. Un login rechazado conserva el nombre intentado como
metadato y deja el actor en null.

Resultados:

| `outcome` | Significado |
|---|---|
| `success` | Acción completada; para login/PDF puede no haber cambios de BD |
| `no_change` | Operación válida sin diferencias de negocio |
| `rejected` | Petición rechazada (4xx) |
| `failed` | Error de ejecución o auditoría (5xx) |

Cada cambio contiene `entityType`, `entityId`, `operation` (`created`, `updated`,
`deleted`), `beforeValues` y `afterValues`. Un alta tiene antes null; una eliminación
física tiene después null. Una baja lógica es un cambio de `status`. En modificaciones
solo se incluyen campos diferentes; los timestamps técnicos se excluyen. Varias
escrituras sobre una fila dentro de la misma acción se consolidan a estado inicial/final.
Los importes y existencias respetan los casts del modelo. La relación producto/categoría
usa `entityType=category_product` e ID compuesto `producto:categoria`.

Los observadores capturan Eloquent dentro de la unidad de auditoría. Las actualizaciones
masivas de estados/sesiones, las líneas eliminadas, las relaciones y los renombrados
SQL tienen captura explícita. Los valores temporales `~rename~` no aparecen.

Los datos de negocio y la auditoría se confirman en la misma transacción/conexión.
Un fallo de escritura de auditoría revierte la operación y responde 503. Se conservan
los primeros bloqueos de categorías/folios y los reintentos existentes; cada reintento
reinicia sus cambios capturados. Los errores de negocio se registran tras revertirlos.
Si falla también el registro del error, queda una alerta técnica. Los rechazos 401/403
se mantienen aunque falle la bitácora.

Las imágenes nuevas se limpian si falla la transacción. Las antiguas se eliminan
después de confirmar mediante `DeleteStoredImages`, con cinco intentos. Revisar los
jobs fallidos; si ni siquiera se puede encolar la limpieza se emite una alerta técnica
y puede quedar un archivo huérfano. La revocación en BD se confirma antes de invalidar
el JWT en caché; la comprobación de sesión en BD sigue rechazando tokens revocados.

### PDF

El PDF se renderiza y serializa antes de confirmar su evento. Solo entonces se devuelve
la respuesta. Un fallo al guardar el evento responde 503 sin bytes PDF. Se registra
pedido, folio, estado, modalidad `inline`/`attachment` y resultado. Esto acredita la
generación y preparación de la respuesta, no que el navegador terminó de descargarla.

### Datos excluidos

Las listas permitidas por modelo están en `config/audit.php`. Nunca se incluyen
contraseñas, hashes de contraseñas, JWT ni `token_hash`, cabeceras de autorización o
archivos completos. Un cambio de contraseña produce `passwordChanged: false → true`.
El logo se representa mediante SHA-256 y tamaño codificado; las imágenes mediante sus
rutas. Se excluyen también claves de credenciales anidadas en configuraciones JSON.

## API de consulta

Todos los endpoints requieren JWT vigente y rol `administrator`:

| Método | Ruta | Respuesta |
|---|---|---|
| GET | `/api/audit-events` | Resúmenes paginados |
| POST | `/api/audit-events/query` | Mismo listado usando filtros de grid |
| GET | `/api/audit-events/{id}` | Metadatos del evento, sin descargar todos sus cambios |
| GET | `/api/audit-events/{id}/changes` | Cambios antes/después paginados |

Siempre se limita el resultado: 15 por defecto y 100 como máximo. Tamaños fuera de
rango producen 422. Orden predeterminado: eventos por `occurredAt DESC, id DESC` y
cambios por `id DESC`. No hay endpoint de exportación sin límites ni de edición/borrado.

Se conservan las convenciones existentes:

- GET: `p[page]` es página desde 1; `p[per_page]` es tamaño.
- POST: `p.page` es desplazamiento desde 0; `p.per_page` es tamaño.
- `p.r` y `p.s` son los alias de desplazamiento/tamaño que usan los grids.
- `totalCount` es true por defecto; false evita la consulta COUNT.
- `f` selecciona campos permitidos del resumen; `o` ordena y `w` filtra mediante
  objetos planos o condiciones `{f, ao, v, lo}`. Se preserva la agrupación AND/OR de grids.
- La validación (`AuditQueryRequest`) sigue el mismo contrato que `TranslatesGridFilters`:
  `p`/`f`/`o`/`w` vacíos o null equivalen a omitirlos, llaves extra se ignoran,
  `direction`/`lo` no distinguen mayúsculas, `anyof`/`noneof` y operadores desconocidos se
  interpretan como en `mapGridOperator()`. Solo da 422 ante campos fuera de la lista blanca,
  tamaño de página fuera de 1–100, fechas inválidas o valores de filtro mal formados.

Campos de resumen permitidos: `id`, `requestId`, `module`, `action`, `outcome`, `actorId`,
`actorName`, `roleCode`, `roleName`, `entityType`, `entityId`, `source`, `occurredAt`.
En cambios: `id`, `auditEventId`, `entityType`, `entityId`, `operation`.

`w.entityType`/`w.entityId` filtran la entidad principal. Los filtros superiores
`entityType` y `entityId` buscan la pareja tanto en la entidad principal como en los
registros afectados. `dateFrom`/`dateTo` son inclusivos; las fechas sin zona se interpretan
en `app.timezone` (America/Mexico_City por defecto) y se convierten a UTC.

POST /api/audit-events/query

```json
{
  "p": { "page": 0, "per_page": 25 },
  "w": [{ "f": "action", "ao": "==", "v": "orders.received", "lo": "&&" }],
  "entityType": "order",
  "entityId": "15",
  "dateFrom": "2026-11-01",
  "dateTo": "2026-11-06",
  "totalCount": true
}
```

```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "Audit events retrieved.",
  "data": {
    "items": [],
    "page": 1,
    "perPage": 25,
    "hasMore": false,
    "totalCount": 0,
    "summary": [0]
  }
}
```

Cuando `totalCount=false`, se omiten `totalCount` y `summary`. La paginación por
desplazamiento conserva la compatibilidad con los grids: nuevas inserciones entre
peticiones pueden desplazar posiciones; no representa una fotografía congelada del historial.

## Procesos y mantenimiento

Los servicios ya utilizan `Audit::transaction()`. Para un comando/job con acciones propias:

```php
app(\App\Services\AuditTrail::class)->run('catalogs', 'maintenance.category_updated', function () {
    $category = \App\Models\Category::lockForUpdate()->findOrFail(1);
    $category->update(['description' => 'Actualización de mantenimiento']);
});
```

El actor de un proceso nuevo es null (`source=system`). Cada job debe usar su scope
propio; no reutilizar contextos HTTP. Para SQL masivo utilizar `bulkUpdate()` o
`bulkDelete()` dentro de la unidad, o `record()` con valores permitidos antes/después.
No enviar payloads, credenciales ni excepciones completas a `metadata()`.

La captura inicia con la activación. Del pasado solo se puede importar el historial que
ya existía en tablas propias (sección siguiente). No se registran
seeders/migraciones, caché, colas o `document_sequences` como cambios de negocio.
El folio asignado sí aparece en el pedido. Los cambios hechos fuera de esta capa
(SQL externo, consola SQL/Tinker con escrituras directas, eventos silenciados) requieren
captura explícita o una futura solución de auditoría de BD. Esta bitácora no impide
alteraciones realizadas por un administrador de MariaDB.

El canal JSON `api_requests` conserva logs técnicos rotativos por 14 días en
`storage/logs/api-requests-*.log`. Registra ruta, usuario autenticado, estado y duración;
no cuerpos ni query strings. Buscar `audit.write_failed`, `auth.blacklist_failed` e
`images.cleanup_dispatch_failed`. Los eventos de BD no tienen borrado automático.
Supervisar tamaño de ambas tablas, eventos diarios y duración de las consultas.

## Historial previo a la auditoría

Antes de la bitácora ya existían tres historiales con fecha y usuario. Se importan con:

```bash
php artisan audit:backfill-legacy --dry-run   # solo cuenta lo que se importaría
php artisan audit:backfill-legacy             # importa
php artisan audit:backfill-legacy --only=kardex --only=orders   # fuentes: kardex, orders, sessions
```

| Fuente | Evento generado | Cambios antes/después |
|---|---|---|
| `inventory_movements` manuales | `inventory.movements_registered` (módulo `inventory`) | Existencia de la variante y alta del movimiento |
| `inventory_movements` de recepción (`order_detail`) | `orders.received` (módulo `orders`) | Existencia, alta del movimiento, `quantityReceived` acumulado de la línea y, si hubo, el cambio de estado de esa recepción |
| `order_status_history` | `orders.created`, `orders.confirmed`, `orders.cancelled`, `orders.received` | `status` del pedido (y `dateReceived` al completarse) y alta del historial |
| `user_sessions` | `auth.login` (módulo `security`) | Alta de la sesión (`idUser`, `expiresAt`), sin token |

Reglas:

- `source = legacy`, `outcome = success`, sin ruta, método, IP ni agente.
  `metadata = { "backfilled": true, "actorDataIsCurrent": true, "legacy": { "<tabla>": [ids] } }`.
- **Actor y rol son los actuales** del usuario: las tablas originales solo guardaron su ID.
- `occurredAt` es el `created_at` original convertido de `app.timezone` a UTC (precisión de segundos).
- Los movimientos se agrupan como los generó una petición: mismo segundo, usuario,
  referencia (pedido) y notas. Dos peticiones idénticas en el mismo segundo quedan en un evento.
- Idempotente: se omite cada fila cuya alta ya existe en `audit_changes` (capturada en vivo o
  ya importada). Se puede volver a ejecutar sin duplicar y no requiere fecha de corte.
- Ejecutar `kardex` y `orders` juntos (o `kardex` primero): si se importa `orders` sin
  `kardex`, el estado de cada recepción queda en un evento aparte de sus movimientos.
- No se importa (no existe el dato): catálogos, productos y usuarios (quién/qué cambió),
  registros borrados, logins fallidos, 401/403, cierres de sesión (`revoked_at` no distingue
  la causa), PDF y ediciones de pedidos en borrador.

## Verificación

```bash
php artisan test --compact
php scripts/verify-audit-mariadb.php
```

La segunda orden requiere acceso a MariaDB y permisos CREATE/DROP DATABASE. Crea
exclusivamente una BD `tienda_v2_audit_test_<aleatorio>`, ejecuta las pruebas de auditoría,
creaciones/renombrado/folios/recepciones concurrentes y una prueba de 100,000 eventos
con EXPLAIN. La elimina en finally. No migra ni limpia la BD de la aplicación.

Para un endpoint o campo nuevo: clasificar el handler en `AuditActions`, revisar la
lista permitida, usar la unidad transaccional y añadir cobertura del efecto de negocio,
incluyendo cascadas y comportamiento ante fallo de auditoría.
