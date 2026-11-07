# Entrega: auditoría del backend → frontend

Guía para construir la pantalla **Logs de auditoría** y mostrar el historial de un
registro. Incluye el contrato de la API y las reglas necesarias para interpretar los datos.

| Recurso | Ubicación |
|---|---|
| Rama de esta entrega | `feature/audit-logs` |
| Documentación técnica y despliegue | [auditoria.md](auditoria.md) |
| OpenAPI / Postman | `public/docs/openapi.yaml`, `public/docs/collection.json`, grupo **Audit** |
| Contrato de entrada | `app/Http/Requests/Audit/AuditQueryRequest.php` |
| Contrato de salida | `app/Services/AuditQueryService.php` |
| Pruebas del contrato | `tests/Feature/AuditQueryTest.php` |

## 1. Qué se implementó y qué debe mostrar la UI

El backend captura automáticamente acciones de negocio y seguridad. Cada **evento**
describe una acción; sus **cambios** contienen los registros afectados y sus valores
antes/después. Una recepción de pedido puede tener un evento con cambios en pedido,
líneas, existencias, movimientos e historial de estados.

Se incluye autenticación, usuarios, asignaciones de rol, sesiones, inventario, pedidos,
generación de PDF, productos, variantes, imágenes, catálogos, empresa y monedas.
Los historiales actuales de pedidos y el Kardex siguen disponibles.

Para frontend se propone:

1. Menú **Auditoría**, visible únicamente para `user.roleCode === 'administrator'`.
2. Grid con fecha, usuario, rol, módulo, acción, resultado, entidad, ID y origen.
3. Filtros por fechas, usuario, rol, módulo, acción, resultado y registro afectado.
4. Botón **Ver detalle**: cargar metadatos y una segunda tabla paginada de cambios.
5. Opcional: botón **Ver auditoría** en el detalle de productos, pedidos o usuarios,
   usando los filtros superiores `entityType` y `entityId` (§5).

La UI consume los eventos que el backend ya genera; no debe enviar peticiones
adicionales para crear logs. No existen endpoints para editar o borrar la bitácora.

### Alcance del historial

- La captura empieza al activar esta versión/módulo. No existe reconstrucción del pasado.
- Los listados, búsquedas, dashboard, detalles y simulaciones `dryRun` no generan eventos
  de negocio; sí se auditan sus rechazos 401/403. La generación del PDF sí tiene evento.
- Las consultas técnicas se escriben en archivos del servidor y no forman parte de esta API.
- No se guarda contenido de PDFs/imágenes, contraseñas, hashes ni JWT. Los logos tienen
  huella SHA-256 y tamaño; las imágenes conservan referencias a sus rutas.
- La cobertura corresponde a la aplicación. Cambios por SQL externo no aparecen automáticamente.

## 2. Autenticación y endpoints

Todas las rutas usan el prefijo `/api`, JWT vigente y rol `administrator`:

```http
Authorization: Bearer <JWT>
Accept: application/json
Content-Type: application/json
```

`Content-Type` es necesario al enviar JSON. Los roles `seller` y `warehouse` reciben
403 al consultar auditoría. Ocultar el menú no sustituye el manejo de ese error.

| Método | Endpoint | Para qué sirve |
|---|---|---|
| GET | `/api/audit-events` | Resúmenes paginados de eventos |
| POST | `/api/audit-events/query` | Mismo listado, con filtros JSON para grids |
| GET | `/api/audit-events/{id}` | Detalle y contexto de un evento |
| GET | `/api/audit-events/{id}/changes` | Cambios antes/después, paginados por separado |

Al abrir el detalle, consultar los dos últimos endpoints. El listado no trae
`metadata`, IP, agente del cliente ni valores antes/después. El detalle del evento
tampoco incluye automáticamente todos sus cambios.

## 3. Listado, respuesta y paginación

La paginación se aplica siempre: **15 registros por defecto y máximo 100**. Omitir
`p` devuelve la primera página. Solicitar un tamaño fuera del rango 1–100 produce 422.

### GET: número de página desde 1

```http
GET /api/audit-events?p[page]=1&p[per_page]=25
```

### POST: desplazamiento de filas desde 0

```http
POST /api/audit-events/query
```

```json
{
  "p": { "page": 0, "per_page": 25 },
  "o": { "column": "occurredAt", "direction": "desc" },
  "totalCount": true
}
```

**Importante:** `p.page` tiene significados diferentes en GET y POST, igual que los grids
existentes. En POST usar `skip`, no el número visible de página:

| Página visible | GET `p.page` | POST `p.page` o `p.r` | Tamaño |
|---|---:|---:|---:|
| 1 | 1 | 0 | 25 |
| 2 | 2 | 25 | 25 |
| 3 | 3 | 50 | 25 |

También se aceptan `p.r` (desplazamiento) y `p.s` (tamaño). Elegir un formato por
petición; los desplazamientos deben corresponder a múltiplos del tamaño de página.

Ejemplo de respuesta del listado:

```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "Audit events retrieved.",
  "data": {
    "items": [{
      "id": 501,
      "requestId": "019a55b7-cb40-7000-8000-000000000001",
      "module": "orders",
      "action": "orders.received",
      "outcome": "success",
      "actorId": 7,
      "actorName": "almacen1",
      "roleCode": "warehouse",
      "roleName": "Almacén",
      "entityType": "order",
      "entityId": "15",
      "source": "api",
      "occurredAt": "2026-11-06T18:25:30.123456Z"
    }],
    "page": 1,
    "perPage": 25,
    "hasMore": false,
    "totalCount": 1,
    "summary": [1]
  }
}
```

- `data.page` en la respuesta siempre es número de página desde 1.
- `totalCount` es el total filtrado, no la cantidad de elementos de esa página.
- `summary: [totalCount]` conserva el formato actual del grid; no es una suma de importes.
- `totalCount: false` omite `totalCount` y `summary`, y evita el COUNT. En ese modo usar
  `hasMore` para avanzar; no se conoce el número total de páginas.
- Orden predeterminado: eventos por `occurredAt DESC, id DESC`; cambios por `id DESC`.
  `o` acepta un criterio; el backend agrega el ID como desempate cuando corresponde.
- Nuevas inserciones pueden desplazar las páginas. Al actualizar filtros o refrescar,
  volver a la primera página; no interpretar el historial como una fotografía congelada.

### Diccionario de campos del evento

| Campo | Tipo JSON | Interpretación para la UI |
|---|---|---|
| `id` | number | ID del evento, usado en `/audit-events/{id}` |
| `requestId` | string | Correlación de la petición; útil para soporte |
| `module` | string | Área funcional; etiquetas sugeridas en §7 |
| `action` | string | Código estable de la acción |
| `outcome` | string | `success`, `no_change`, `rejected` o `failed` |
| `actorId` | number o null | Usuario autenticado que actuó, cuando existe |
| `actorName` | string o null | **Nombre de usuario histórico**, no nombre completo |
| `roleCode` / `roleName` | string o null | Rol histórico al realizar la acción |
| `entityType` | string o null | Tipo de la entidad principal |
| `entityId` | string o null | Identificador de esa entidad; conservar como texto |
| `source` | string | `api` o `system` |
| `occurredAt` | string | Fecha UTC ISO 8601, con sufijo `Z` |

Usar `id` como clave de fila. Varios eventos pueden compartir `requestId` cuando
una petición contiene más de una acción de seguridad.

Mostrar `actorName` y `roleName` guardados, sin sustituirlos por datos actuales de
usuarios o roles: pueden haber cambiado. Si `source=system` y no hay actor, mostrar
**Proceso del sistema**; si `source=api` y no hay actor, **No autenticado**.

Para mostrar la hora local, por ejemplo:

```js
new Intl.DateTimeFormat('es-MX', {
  dateStyle: 'short', timeStyle: 'medium', timeZone: 'America/Mexico_City'
}).format(new Date(event.occurredAt));
```

Conservar la cadena UTC original para detalle/copia. Los nulos y un actor ausente
son datos válidos y no representan un error de carga.

## 4. Filtros y ordenamiento

En POST los filtros van en JSON; en GET se serializan como query params. Las llaves
públicas usan camelCase. Los campos permitidos del resumen son los de la tabla anterior.

### Filtros simples

```json
{
  "p": { "r": 0, "s": 25 },
  "w": { "module": "orders", "outcome": "success", "actorId": 7 },
  "dateFrom": "2026-11-01",
  "dateTo": "2026-11-06",
  "o": { "column": "occurredAt", "direction": "desc" },
  "totalCount": true
}
```

Las fechas son inclusivas. Una fecha sin hora se interpreta en la zona del backend
(`America/Mexico_City` por defecto); `dateTo` incluye ese día completo. También se
aceptan timestamps con zona explícita. Cambiar cualquier filtro debe reiniciar `skip=0`.

### Condiciones de grid

```json
{
  "p": { "page": 0, "per_page": 25 },
  "w": [
    { "f": "action", "ao": "==", "v": "orders.confirmed", "lo": "&&" },
    { "f": "action", "ao": "==", "v": "orders.cancelled", "lo": "||" },
    { "f": "actorId", "ao": "==", "v": 7, "lo": "&&" }
  ]
}
```

Este ejemplo significa `(confirmación OR cancelación) AND usuario 7`. Un bloque de
condiciones `||` se agrupa con la condición anterior y los bloques se unen con AND.
No enviar el árbol anidado de DevExtreme sin adaptarlo a este contrato.

Operadores aceptados: `==`, `!=`, `<>`, `>`, `>=`, `<`, `<=`, `contains`, `notcontains`,
`startswith`, `endswith`, `in`, `notin`, `between`, y los alias `anyof`/`noneof` del
filterBuilder. `in`/`notin` llevan un array no vacío (máximo 100 valores); `between`
lleva dos valores. Un operador desconocido se trata como `=` (o `in` si el valor es un
array), igual que en los demás grids. `occurredAt` acepta comparaciones y rangos de
fecha, no operadores de texto. Máximo 50 condiciones.

Las direcciones de orden son `asc`/`desc`, sin distinguir mayúsculas; `lo` también.
`o.field`/`o.type` son alias de `o.column`/`o.direction`. Usar ordenamiento de una sola
columna.

`f`, si se usa, es un array de campos del resumen que se desean cargar; el backend
siempre incluye `id`. No solicitar `metadata`, `beforeValues` o `afterValues` en el
listado: se obtienen desde los endpoints de detalle. Omitir `f`, o enviarlo como `[]`
o `null`, devuelve todos los campos del resumen.

### Mismo contrato que los demás grids

El payload del grid compartido funciona igual que en `/users/query` y el resto de
endpoints: `p`, `f`, `o` y `w` vacíos o `null` equivalen a omitirlos, las llaves
desconocidas se ignoran y `totalCount` no booleano se interpreta como `false`. Solo hay
dos diferencias, intencionales:

| Regla | Otros grids | Auditoría |
|---|---|---|
| Paginación | Sin `p` devuelven todo | Siempre paginada: 15 por defecto, máximo 100 |
| Campos en `f`, `o`, `w` | Columnas de la tabla | Solo los del resumen; otro campo da 422 |

## 5. Historial de un registro, detalle y comparación

Para ver todo lo que afectó al pedido 15, incluidos eventos cuya entidad principal
sea otra, usar los filtros **superiores**:

```json
{
  "p": { "page": 0, "per_page": 25 },
  "entityType": "order",
  "entityId": "15",
  "totalCount": true
}
```

La pareja se busca en la entidad principal y en los cambios asociados. En cambio,
`w.entityType`/`w.entityId` filtran únicamente la entidad principal del evento.

### Metadatos del evento

`GET /api/audit-events/501` devuelve el mismo envoltorio y `data` como objeto, con
todos los campos del resumen y además:

| Campo adicional | Tipo | Uso |
|---|---|---|
| `route` | string o null | Plantilla de ruta, por ejemplo `api/orders/{order}/receive` |
| `method` | string o null | Método HTTP |
| `ip` | string o null | IP registrada |
| `userAgent` | string o null | Agente del cliente |
| `metadata` | object, array vacío o null | Datos adicionales según la acción |

`route` es una plantilla, no una URL lista para navegar. Usar `entityType`/`entityId`
para construir enlaces a pantallas conocidas de la aplicación. Si el registro ya no
existe, seguir mostrando su auditoría.

Los metadatos son variables. Pueden incluir `attemptedUserName` para login,
`reason` para motivos de rechazo, o `httpStatus`/`exceptionType` ante errores. No asumir
que todas las acciones tienen las mismas claves; permitir también una vista JSON.

Para `orders.pdf_generated`, por ejemplo:

```json
{
  "folio": "PED-0000015",
  "orderStatus": "partially_received",
  "delivery": "attachment"
}
```

Etiquetar `inline` como **Visualización** y `attachment` como **Descarga**. El evento
acredita la generación y preparación de la respuesta; no confirma que el navegador
terminó de guardar el archivo. **El módulo del PDF es `documents`, aunque la acción
empiece por `orders.`**

### Cambios antes/después

```http
GET /api/audit-events/501/changes?p[page]=1&p[per_page]=1
```

```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "Audit changes retrieved.",
  "data": {
    "items": [{
      "id": 901,
      "auditEventId": 501,
      "entityType": "product_variant",
      "entityId": "101",
      "operation": "updated",
      "beforeValues": { "stock": "10.000" },
      "afterValues": { "stock": "12.000" }
    }],
    "page": 1,
    "perPage": 1,
    "hasMore": true,
    "totalCount": 5,
    "summary": [5]
  }
}
```

Se usa tamaño 1 en este ejemplo para ilustrar su paginación independiente. La UI
puede usar 15 o 25. Este endpoint acepta `p`, `w`, `o`, `f` y `totalCount`; sus campos
filtrables/ordenables/proyectables son `id`, `auditEventId`, `entityType`, `entityId`,
`operation`. Para cargar `beforeValues`/`afterValues`, omitir `f` en esta consulta.
Las fechas y los filtros superiores del historial se aplican al listado
de eventos, no al endpoint `/changes`.

| `operation` | Antes | Después | Presentación |
|---|---|---|---|
| `created` | null | Valores permitidos del alta | Registro creado |
| `updated` | Campos modificados | Nuevos valores de esos campos | Comparación por campo |
| `deleted` | Valores permitidos anteriores | null | Registro eliminado físicamente |

Una baja lógica es `updated` con `status: active → inactive`; no confundirla con
eliminación física. Las claves/SKU en cascada muestran valores iniciales/finales.

Para la comparación:

- Construir filas con la unión de claves de `beforeValues` y `afterValues` y columnas
  **Campo / Antes / Después**. No esperar un esquema fijo para todas las entidades.
- Distinguir clave ausente, valor null, cadena vacía, false y cero. Conservar los tipos.
- Los decimales pueden ser strings; no convertir todos los valores a número.
  `entityId` es string y puede ser compuesto: `category_product` usa `producto:categoria`.
- Arrays/objetos se muestran como JSON escapado. Renderizar también textos como texto,
  sin interpretarlos como HTML. Los campos de BD se presentan en camelCase.
- `passwordChanged: false → true` significa que se cambió la contraseña; no hay valores
  recuperables. Las rutas históricas de imágenes pueden dejar de existir.
- Un PDF, un login reutilizando sesión, un rechazo o una operación sin cambios pueden
  tener `items: []`. Mostrar **Sin cambios de datos registrados**, conservando el evento.
- Un error/rechazo no presenta los cambios revertidos como cambios exitosos.

## 6. Ejemplo de carga desde el frontend

Ejemplo TypeScript adaptable al cliente HTTP actual. `apiBase` incluye `/api` y
`token` se obtiene del flujo de autenticación existente:

```ts
type AuditPage<T> = {
  items: T[];
  page: number;
  perPage: number;
  hasMore: boolean;
  totalCount?: number;
  summary?: number[];
};

async function loadAuditEvents<T>(
  apiBase: string,
  token: string,
  skip = 0,
  take = 25,
  filters: Record<string, unknown> = {},
): Promise<AuditPage<T>> {
  const response = await fetch(`${apiBase}/audit-events/query`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      ...filters,
      p: { r: skip, s: take },
      totalCount: true,
    }),
  });
  const body = await response.json();
  if (!response.ok) {
    throw Object.assign(new Error(body.message ?? 'No se pudo cargar la auditoría.'), {
      status: response.status,
      errors: body.errors,
      requestId: response.headers.get('X-Request-Id'),
    });
  }
  return body.data;
}
```

En un `CustomStore` de DevExtreme, mapear `loadOptions.skip`/`take` a `skip`/`take`
y devolver `{ data: result.items, totalCount: result.totalCount }`. Activar paginación
remota, limitar tamaños a `[15, 25, 50, 100]`, y adaptar filtros/orden a §4. No usar
la opción de cargar todos los registros. Los parámetros del detalle se cargan con GET
y su número de página desde 1.

## 7. Etiquetas sugeridas

### Resultado y módulos

| `outcome` | Etiqueta | Significado |
|---|---|---|
| `success` | Completado | Acción exitosa, con o sin cambios de BD |
| `no_change` | Sin cambios | Operación válida sin diferencias de negocio |
| `rejected` | Rechazado | Petición rechazada (4xx) |
| `failed` | Fallido | Error de ejecución/auditoría (5xx) |

| `module` | Etiqueta |
|---|---|
| `security` | Seguridad |
| `users` | Usuarios |
| `inventory` | Inventario |
| `orders` | Pedidos |
| `documents` | Documentos / PDF |
| `products` | Productos e imágenes |
| `catalogs` | Catálogos |
| `configuration` | Configuración |

### Acciones y entidades

| Acciones | Etiqueta base |
|---|---|
| `auth.login`, `auth.logout`, `auth.sessions_revoked` | Inicio de sesión, cierre de sesión, revocación de sesiones |
| `security.unauthenticated`, `security.access_denied` | Acceso sin autenticación válida, acceso sin permiso |
| `users.created/updated/deactivated` | Usuario creado/modificado/desactivado |
| `inventory.movements_registered` | Movimientos de inventario registrados |
| `orders.created/updated/confirmed/cancelled/received` | Pedido creado/modificado/confirmado/cancelado; mercancía recibida |
| `orders.pdf_generated` | PDF de pedido generado |
| `products.created/updated/deactivated` | Producto creado/modificado/desactivado |
| `products.image_changed`, `products.image_deleted` | Imagen principal cambiada/eliminada |
| `variants.created/updated/deactivated` | Variantes creadas/modificadas/desactivadas |
| `product_images.created/deleted` | Imágenes de producto/color agregadas/eliminadas |
| `categories`, `size_groups`, `sizes`, `colors` + `.created/.updated/.deactivated` | Categoría, grupo, talla o color creado/modificado/desactivado |
| `company_info.created/updated` | Configuración de empresa creada/modificada |
| `currencies.created/updated/deactivated` | Moneda creada/modificada/desactivada |

Las barras de esta tabla abrevian códigos distintos: por ejemplo `users.created`,
`users.updated` y `users.deactivated`. El resultado de un login se distingue por
`outcome`; no existen códigos separados `auth.login_success`/`auth.login_failed`.

Entidades actuales: `user`, `user_type`, `user_session`, `product`, `product_variant`,
`product_image`, `category`, `category_product`, `size_group`, `size`, `color`, `currency`,
`company_info`, `order`, `order_detail`, `order_status_history`, `inventory_movement`.
Solo construir enlaces para entidades cuyas pantallas existan. Para un código futuro
desconocido, mostrar el código original como respaldo y conservar el detalle JSON.

## 8. Errores, PDF y criterios de aceptación

| HTTP | Comportamiento esperado |
|---|---|
| 401 | Usar el manejo actual de sesión expirada/invalidada |
| 403 | Mostrar falta de permiso; no reintentar automáticamente |
| 404 | Evento inexistente: cerrar/mostrar detalle no disponible |
| 422 | Mostrar errores de filtros/paginación y corregir la petición |
| 503 en operación auditada | Informar que se canceló; no mostrar éxito ni asumir que el cambio ocurrió |

Las respuestas exitosas usan `{ok, code, status, message, data}`. Los errores de
validación 422 pueden usar el formato estándar de Laravel `{message, errors}` sin
`ok` ni `data`. Comprobar siempre el estado HTTP y soportar ambos formatos.

`X-Request-Id` está expuesto por CORS: puede leerse desde el navegador y mostrarse
en errores para soporte. Es el ID de esa petición; al consultar la bitácora, el
`requestId` de cada fila corresponde a su operación original y será distinto.

La generación del PDF sigue en `GET /api/orders/{id}/pdf` y `?download=1`. Si falla
su auditoría responde 503 con JSON, no PDF. En el flujo que usa `blob`, comprobar
`response.ok` antes de crear el archivo/URL; si falla, leer el JSON de error.

Checklist para la pantalla:

- Administrador puede entrar; vendedor/almacén no ven el menú y se maneja el 403.
- Primera página, cambio de página/tamaño y filtros usan el contrato correcto de GET/POST.
- Detalle y cambios tienen carga, error, vacío y paginación independientes.
- Se distinguen null, false, cero, string decimal, ID compuesto y JSON.
- Usuario/rol históricos y acciones sin actor se presentan correctamente.
- PDF aparece como `module=documents`, con resultado y modalidad, aunque no tenga cambios.
- Una baja lógica no aparece como eliminación física.
- Los errores 422/503 y el ID de petición se presentan correctamente.
- Al cambiar filtros se vuelve a la primera página y se puede refrescar el historial.

Antes del despliegue del frontend, el entorno debe tener el backend de esta entrega,
su migración aplicada y los módulos habilitados. La configuración por etapas y la
validación del servidor están descritas en [auditoria.md](auditoria.md).
