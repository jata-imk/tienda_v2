# Inventario (V2)

Modelo de inventario con **producto base** separado de sus **existencias por variante**
(producto + talla + color), catálogos de tallas/colores e historial de movimientos.

Todos los endpoints requieren:
- Header `Authorization: Bearer <JWT>`
- Usuario con tipo `administrador`

Las respuestas usan el formato estándar `ApiResponse` (`{ ok, code, status, message, data }`).
Los listados (`index` / `query`) soportan los filtros `p[page]`, `p[per_page]`, `f[]`,
`o[column]`, `o[direction]`, `w[column]`, `totalCount` (igual que `products`).

### Operadores de `POST /{recurso}/query`

El `w` en formato array (`{f, ao, v, lo}`) es el que manda el `filterRow` de DevExtreme.
`TranslatesGridFilters` traduce estos operadores:

| `ao` | SQL resultante |
|---|---|
| `==` (default) | `= valor` |
| `!=` / `<>` | `!= valor` |
| `>`, `>=`, `<`, `<=` | comparación directa |
| `contains` | `LIKE '%valor%'` |
| `notcontains` | `NOT LIKE '%valor%'` |
| `startswith` | `LIKE 'valor%'` |
| `endswith` | `LIKE '%valor'` |
| `between` (`v: [a, b]`) | `BETWEEN a AND b` |
| `in` (`v: [1, 2, 3]`) | `IN (...)`. Alias: `anyof` (filterBuilder de DevExtreme) |
| `notin` (`v: [1, 2, 3]`) | `NOT IN (...)`. Alias: `not in`, `noneof` |

Los `%` y `_` dentro del texto se escapan, así que no actúan como comodines.
Un `ao` desconocido cae en `=` si el valor es escalar; si el valor es un **array**
se trata como `in` (nunca se colapsa la lista a su primer elemento).

### Agrupación lógica de `lo`

`lo` conecta cada condición con la anterior. Una condición `&&` seguida de una o
más `||` forma **un solo grupo OR**, que se AND-ea con el resto. Este `w`:

```jsonc
[
  { "f": "status",      "ao": "==",       "v": "active",  "lo": "&&" },
  { "f": "categories",  "ao": "in",       "v": [1, 2],    "lo": "&&" },
  { "f": "name",        "ao": "contains", "v": "asas",    "lo": "&&" },
  { "f": "key",         "ao": "contains", "v": "asas",    "lo": "||" },
  { "f": "categories",  "ao": "contains", "v": "asas",    "lo": "||" },
  { "f": "idSizeGroup", "ao": "contains", "v": "asas",    "lo": "||" }
]
```

se evalúa como:

```text
status = active
AND categories IN (1, 2)
AND ( name LIKE '%asas%' OR key LIKE '%asas%'
      OR categoría LIKE '%asas%' OR grupo de tallas LIKE '%asas%' )
```

Los `||` **no** se aplican planos: si lo hicieran, el `status` y el filtro de
categorías se perderían por la precedencia de SQL.

### Campos que son relaciones, no columnas

| Condición | Se resuelve como |
|---|---|
| `categories` + `in` / `notin` (ids) | `whereHas('categories', … whereIn('categories.id', $ids))` |
| `categories` + `contains` (texto) | busca en `categories.name`, no en el id |
| `categories` + `==` (id) | `whereHas('categories', … categories.id = $id)` |
| `idSizeGroup` + `contains` (texto) | busca en `size_groups.name` vía la relación |
| `idSizeGroup` + `==`, `>`, … | comparación directa contra la columna `id_size_group` |

Un `in` con lista vacía se ignora (no filtra), igual que una selección vacía en
el frontend. Un producto que caiga en varias categorías seleccionadas aparece
**una sola vez**: el filtro compila a un `EXISTS` correlacionado, no a un `JOIN`.

### `search` — extensión propia (no forma parte del contrato)

Además de lo anterior, el backend acepta el campo virtual
`{ "f": "search", "ao": "contains", "v": "texto" }`, que arma del lado servidor
el mismo grupo OR sobre campos clave del recurso. Disponible en:
- `products`: `name`, `key`, `description`, `code_bar`, categoría y grupo de tallas.
- `categories`: `name` y `description`.
- `colors`: `name` y `hex_color`.
- `sizes`: `name` y nombre del grupo de tallas.
- `size-groups`: `name` y `description`.
- `users`: nombre, apellidos, usuario y email.
- `inventory/movements`: `notes`, `reference_type`, SKU de variante y nombre/clave del producto.

Un término vacío o sólo con espacios se ignora.

---

## Modelo de datos

| Tabla | Rol |
|---|---|
| `products` | Producto base (datos comerciales y fiscales). Una fila por producto. |
| `categories` | Clasificación de productos. |
| `category_product` | Pivote producto ↔ categoría (un producto puede tener varias). |
| `size_groups` | Grupos de tallas (Adultos, Niños). Catálogo. |
| `sizes` | Tallas por grupo (con `sort_order`). Catálogo. |
| `colors` | Colores reutilizables (`hex_color`). Catálogo. |
| `product_variants` | Existencia real por `producto + talla + color`. Único `id_product+id_size+id_color`. |
| `product_images` | Galería de imágenes por `producto + color` (varias filas por combinación). |
| `inventory_movements` | Historial de cada entrada/venta/ajuste/devolución/cancelación. |

`products` ya **no** guarda `size` ni `stock`: la existencia vive en `product_variants.stock`
y la existencia total del producto es la suma de variantes activas (`totalStock`).
`products.id_size_group` limita qué tallas pueden usar sus variantes.

---

## Catálogos — CRUD completo

Mismo patrón para los tres. `GET`, `GET /{id}`, `POST`, `PUT /{id}`, `DELETE /{id}` (desactiva).
Además `POST /{recurso}/query`.

### `/api/size-groups`
| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `name` | string | sí | Nombre del grupo (ej. Adultos) |
| `description` | string | no | Descripción |
| `status` | string | no | active / inactive |

### `/api/sizes`
| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `idSizeGroup` | int | sí | FK → size_groups |
| `name` | string | sí | Talla visible (32, M, …) |
| `sortOrder` | int | no | Orden de despliegue (default 0) |
| `status` | string | no | active / inactive |

Filtra tallas de un grupo con `w[id_size_group]`.

### `/api/colors`
| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `name` | string | sí | Nombre del color |
| `hexColor` | string | no | Hex `#RRGGBB` para muestra visual |
| `status` | string | no | active / inactive |

---

## `/api/products` — CRUD + variantes

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/products` | Listar (incluye `variants` y `totalStock`) |
| `POST` | `/api/products/query` | Listar con filtros avanzados |
| `GET` | `/api/products/{id}` | Ver uno con variantes |
| `GET` | `/api/products/{id}/variants` | Variantes del producto (soporta filtros de grid) |
| `POST` | `/api/products/{id}/variants/query` | Consultar variantes del producto (filtros en body) |
| `POST` | `/api/products` | Crear producto + variantes + movimientos iniciales |
| `PUT` | `/api/products/{id}` | Actualizar datos base del producto |
| `POST` | `/api/products/{id}/variants` | Agregar una o varias variantes a un producto existente |
| `PUT` | `/api/products/{id}/variants/{variantId}` | Editar `sku`, `codeBar` o `status` de una variante |
| `DELETE` | `/api/products/{id}/variants/{variantId}` | Desactivar variante (status → inactive) |
| `POST` | `/api/products/{id}/image` | Subir/reemplazar imagen de portada (multipart) |
| `DELETE` | `/api/products/{id}/image` | Borrar imagen de portada y thumbnail |
| `GET` | `/api/products/{id}/colors/{colorId}/images` | Listar imágenes de un color |
| `POST` | `/api/products/{id}/colors/{colorId}/images` | Agregar una o varias imágenes a un color (multipart) |
| `DELETE` | `/api/products/{id}/colors/{colorId}/images/{imageId}` | Borrar una imagen puntual del color |
| `DELETE` | `/api/products/{id}` | Desactivar (status → inactive) |

### Campos del producto base
| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `categories` | int[] | sí | **Array** de FKs → categories (ej. `[1, 2]`) |
| `idSizeGroup` | int | cond. | FK → size_groups. Requerido si `stockControl=true` |
| `key` | string | sí | Clave interna única |
| `name` | string | sí | Nombre |
| `description` | string | no | Descripción |
| `codeBar` | string | no | Código de barras general |
| `price` | number | sí | Precio base **sin IVA** |
| `cost` | number | sí | Costo |
| `stockControl` | bool | sí | Si maneja existencias |
| `discount` | number | no | % descuento (default 0) |
| `typeIva` | int | sí | 1=general, 2=tasa, 3=cuota, 4=no aplica |
| `rateIva` | number | cond. | Tasa IVA (typeIva=2) |
| `quotaIva` | number | cond. | Cuota IVA (typeIva=3) |
| `isr` | number | no | % ISR (default 0) |
| `impEsp` | number | no | % impuesto especial (default 0) |
| `status` | string | no | active / inactive |
| `variants` | array | cond. | Variantes talla×color. Requerido si `stockControl=true` |
| `initialMovement` | object | no | Instrucción para generar el movimiento inicial por variante |

### Campos de cada variante (`variants[]`)
| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `idSize` | int | sí | FK → sizes. Debe pertenecer a `idSizeGroup` |
| `idColor` | int | sí | FK → colors |
| `sku` | string | sí | Clave única de la variante |
| `codeBar` | string | no | Código de barras de la variante |
| `stock` | number | sí | Existencia inicial (puede ser 0) |
| `status` | string | no | active / inactive |

No se permite repetir la combinación `idSize + idColor` dentro del payload.

### `initialMovement` (opcional)
| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `movementType` | string | sí* | entry / sale / adjustment / return / cancel |
| `referenceType` | string | no | Origen (ej. `initial_load`) |
| `referenceId` | int | no | Documento origen |
| `notes` | string | no | Comentario |
| `idUser` | int | sí* | Usuario que genera el movimiento |

\* Requeridos sólo si se envía `initialMovement`. El backend crea **un movimiento por cada
variante con `stock > 0`** (`previous_stock=0`, `new_stock=stock`) dentro de una transacción.

### Categorías del producto — entrada vs salida

El campo se llama `categories` en ambos sentidos. La **entrada** (`POST`/`PUT`) es un array de
ids; la **salida** trae el catálogo ya resuelto:

```jsonc
// Request
{ "categories": [1, 2] }

// Response
{ "categories": [ { "id": 1, "desc": "Camisas lino" }, { "id": 2, "desc": "Caballero" } ] }
```

La entrada también acepta los objetos completos (`[{ "id": 1 }, { "id": 2 }]`), por si el
`dxTagBox` manda el registro entero en vez del `valueExpr`.

En `PUT`, enviar `categories` **reemplaza** el conjunto completo (`sync`); omitirlo lo deja intacto.
Para filtrar se usa `w[categories]` (también se acepta `w[id_category]`) — el backend lo resuelve
contra la pivote, no contra una columna de `products`.

### Imagen del producto

`POST /api/products/{id}/image` es **multipart/form-data** con un campo `image`
(jpeg/jpg/png/webp, máx. 4 MB). Compatible directo con `dxFileUploader` de DevExtreme:

```ts
uploadUrl: `${API}/products/${id}/image`, name: 'image'
```

El backend guarda el archivo en el disco `public` (`storage/app/public/products/{id}/`),
genera un thumbnail de 200 px (lado mayor) en `.../thumbs/` y reemplaza cualquier imagen previa.
La respuesta trae URLs absolutas:

```json
{ "image": "http://127.0.0.1:8000/storage/products/1/uuid.png",
  "imageThumb": "http://127.0.0.1:8000/storage/products/1/thumbs/uuid.png" }
```

En DB solo se guardan los paths relativos (`image`, `image_thumb`); la URL se arma con `APP_URL`.
**Requiere `php artisan storage:link` una vez por entorno.**

### Imágenes por color

Además de la imagen de portada del producto, cada **color** puede tener su propia
galería de imágenes (por ejemplo, varias fotos de la variante roja). Es independiente
de la talla: todas las tallas de un mismo color comparten la misma galería.

`POST /api/products/{id}/colors/{colorId}/images` es **multipart/form-data** con un
campo `images[]` (uno o varios archivos, jpeg/jpg/png/webp, máx. 4 MB c/u):

```ts
uploadUrl: `${API}/products/${id}/colors/${colorId}/images`, name: 'images[]', uploadMode: 'useForm'
```

A diferencia de la imagen de portada, esta carga **no reemplaza** nada: cada archivo
enviado se agrega como una fila nueva. Para quitar una imagen puntual se usa
`DELETE /api/products/{id}/colors/{colorId}/images/{imageId}`.

```json
{
  "items": [
    { "id": 1, "idProduct": 1, "idColor": 1,
      "image": "http://127.0.0.1:8000/storage/products/1/colors/1/uuid.png",
      "imageThumb": "http://127.0.0.1:8000/storage/products/1/colors/1/thumbs/uuid.png" }
  ]
}
```

`GET /api/products/{id}` incluye estas imágenes en el arreglo plano `colorImages`
(sin agrupar); el frontend agrupa por `idColor` si lo necesita.

### Ejemplo de alta
```json
{
  "categories": [1, 2],
  "idSizeGroup": 1,
  "key": "CAM-001",
  "name": "Camisa lino caballero",
  "description": "Camisa 100% lino manga larga",
  "price": 800,
  "cost": 600,
  "stockControl": true,
  "typeIva": 1,
  "rateIva": 16,
  "variants": [
    { "idSize": 2, "idColor": 1, "sku": "CAM-001-34-BLA", "codeBar": "7500000000011", "stock": 3 },
    { "idSize": 3, "idColor": 1, "sku": "CAM-001-36-BLA", "stock": 1 },
    { "idSize": 4, "idColor": 2, "sku": "CAM-001-38-AZM", "stock": 4 }
  ],
  "initialMovement": {
    "movementType": "entry",
    "referenceType": "initial_load",
    "notes": "Carga inicial de inventario",
    "idUser": 1
  }
}
```

Si `stockControl` es `false`, no se exige `idSizeGroup` ni `variants` (enviar `variants: []`).
Un producto así puede recibir variantes después: primero se activa con
`PUT /api/products/{id}` (`stockControl: true` + `idSizeGroup`) y luego se usa
`POST /api/products/{id}/variants`.

---

## Agregar variantes a un producto existente

`PUT /api/products/{id}` **solo toca los datos base**: ignora `variants`. Para dar de alta un
color o una talla nueva sobre un producto ya creado se usa el subrecurso `/variants`.

### `POST /api/products/{id}/variants`

Mismo formato que el bloque `variants` del alta, más el `initialMovement` opcional:

```json
{
  "variants": [
    { "idSize": 2, "idColor": 3, "sku": "CAM-001-34-BEI", "codeBar": "7500000000021", "stock": 3 },
    { "idSize": 3, "idColor": 3, "sku": "CAM-001-36-BEI", "stock": 2 }
  ],
  "initialMovement": {
    "movementType": "entry",
    "referenceType": "initial_load",
    "notes": "Alta de color Beige",
    "idUser": 1
  }
}
```

```json
// Response 201
{
  "ok": true, "code": 201, "status": "Created", "message": "Variants created.",
  "data": {
    "items": [
      { "id": 7, "idProduct": 1, "idSize": 2, "size": "34", "idColor": 3, "color": "Beige",
        "hexColor": "#D8C3A5", "sku": "CAM-001-34-BEI", "codeBar": "7500000000021",
        "stock": 3, "status": "active" }
    ],
    "totalStock": 16
  }
}
```

Validaciones (`422` si fallan, no se crea nada del lote):

- El producto debe tener `stockControl = true`.
- Cada `idSize` debe pertenecer al `idSizeGroup` del producto.
- La combinación `idSize + idColor` no puede repetirse dentro del payload **ni existir ya** en el
  producto (`product_variants` tiene un unique `id_product + id_size + id_color`).
- `sku` es único en toda la tabla.

Igual que en el alta del producto, `initialMovement` genera **un movimiento por cada variante con
`stock > 0`** (`previous_stock = 0`, `new_stock = stock`). Sin `initialMovement` la variante nace
con su `stock` y sin historial.

Producto inexistente → `404`.

### `PUT /api/products/{id}/variants/{variantId}`

Campos editables: `sku`, `codeBar`, `status`. Los omitidos no se tocan.

`idSize` e `idColor` **no** son editables (identifican la variante y romperían la trazabilidad de
los movimientos): para cambiar de talla o color se desactiva la variante y se crea otra. El `stock`
tampoco: se mueve por `POST /api/inventory/movements`.

Si la variante no pertenece al producto de la URL → `404 Variant not found for this product.`

### `DELETE /api/products/{id}/variants/{variantId}`

Baja lógica: pone `status = inactive`. La fila y sus movimientos se conservan; la variante deja de
sumar en `totalStock`. Se reactiva con `PUT ... { "status": "active" }`.

### Imágenes del color nuevo

`POST /api/products/{id}/colors/{colorId}/images` exige que el color exista en alguna variante del
producto. Al agregar la variante del color nuevo, su galería queda habilitada de inmediato.

---

## `/api/inventory/movements` — Historial (Kardex) y Ajuste de existencias

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/inventory/movements` | Listar movimientos de inventario (soporta filtros de grid) |
| `POST` | `/api/inventory/movements/query` | Consultar movimientos (filtros avanzados en body) |
| `POST` | `/api/inventory/movements` | Registrar uno o varios movimientos de existencias |

### Consulta de movimientos (`GET` y `POST /query`)

Soportan paginación (`p`), selección de campos (`f`), ordenación (`o`), filtros (`w`) y `totalCount`.

Filtros soportados en `w`:
- `movement_type`: `entry`, `sale`, `adjustment`, `return`, `cancel` (o array con `in`)
- `id_product`: ID del producto (resuelto a través de la variante)
- `sku`: SKU puntual de la variante
- `id_user`: usuario que generó el movimiento
- `created_at`: filtros de fecha / rango
- `search`: búsqueda de texto sobre notas, origen, usuario, SKU o nombre del producto

Respuesta estándar (`ApiResponse::query`):
```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "Inventory movements retrieved.",
  "data": {
    "items": [
      {
        "id": 501,
        "idProductVariant": 101,
        "movementType": "adjustment",
        "quantity": 2.0,
        "previousStock": 8.0,
        "newStock": 6.0,
        "referenceType": "manual_adjustment",
        "referenceId": null,
        "notes": "Ajuste por conteo físico",
        "idUser": 1,
        "createdAt": "2026-08-12 21:45:00",
        "userName": "admin",
        "idProduct": 25,
        "productName": "Camisa lino caballero",
        "productKey": "CAM-001",
        "sku": "CAM-001-34-BLA",
        "size": "34",
        "color": "Blanco"
      }
    ],
    "totalCount": 1,
    "summary": [1]
  }
}
```

### Registro de movimientos (`POST /api/inventory/movements`)

`POST` registra uno o varios movimientos sobre variantes de **un mismo producto** y actualiza
su `stock` en una sola operación atómica: si cualquier elemento de `movements` falla, no se
aplica ninguno. Sirve tanto para ajustar una sola variante como para un conteo físico completo.

### Campos generales del registro

| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `idProduct` | int | sí | Producto al que pertenecen todas las variantes. |
| `idUser` | int | sí | Usuario que genera los movimientos. **Debe coincidir con el usuario de la sesión** (se valida contra el JWT); si no coincide, `422`. |
| `referenceType` | string | no | Origen (ej. `manual_adjustment`, `sales_note`) |
| `referenceId` | int | no | Documento origen |
| `notes` | string/null | no | Comentario general, se aplica a cada movimiento generado |
| `movements` | array | sí | Uno o más movimientos (1 a 200 por petición) |

### Campos de cada movimiento (`movements[]`)

| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `idProductVariant` | int | sí | FK → product_variants. No debe repetirse dentro del arreglo. |
| `movementType` | string | sí | entry / sale / adjustment / return / cancel |
| `quantity` | number | sí | Magnitud positiva del movimiento |

**Efecto sobre el stock:** `entry`, `return`, `cancel` **incrementan**; `sale`, `adjustment`
**disminuyen**. Si el resultado de cualquier variante quedara negativo, responde `422` y no se
aplica ningún movimiento del lote.

**Validaciones adicionales:** todas las variantes deben existir y pertenecer a `idProduct`; el
producto debe tener `stockControl=true`. Se permiten movimientos sobre variantes con
`status=inactive` (su stock se actualiza igual), pero **no cuentan** en el `totalStock` de la
respuesta, que solo suma variantes activas.

### Respuesta

`data.movements` es siempre un arreglo, incluso al enviar un solo movimiento. Incluye también
`data.totalStock`, la existencia total vigente del producto tras aplicar el lote.

```json
// Request
{
  "idProduct": 25,
  "idUser": 1,
  "referenceType": "manual_adjustment",
  "notes": "Ajuste por conteo físico",
  "movements": [
    { "idProductVariant": 101, "movementType": "adjustment", "quantity": 2 },
    { "idProductVariant": 102, "movementType": "entry", "quantity": 3 }
  ]
}
```

```json
// Response 201
{
  "ok": true,
  "code": 201,
  "status": "Created",
  "message": "Ajustes de inventario registrados correctamente.",
  "data": {
    "movements": [
      {
        "id": 501, "idProductVariant": 101, "movementType": "adjustment",
        "quantity": 2, "previousStock": 8, "newStock": 6,
        "referenceType": "manual_adjustment", "referenceId": null,
        "notes": "Ajuste por conteo físico", "idUser": 1,
        "createdAt": "2026-08-12 21:45:00"
      },
      {
        "id": 502, "idProductVariant": 102, "movementType": "entry",
        "quantity": 3, "previousStock": 4, "newStock": 7,
        "referenceType": "manual_adjustment", "referenceId": null,
        "notes": "Ajuste por conteo físico", "idUser": 1,
        "createdAt": "2026-08-12 21:45:00"
      }
    ],
    "totalStock": 13
  }
}
```

Error de existencia insuficiente (`422`), no se aplica nada del lote:

```json
{
  "ok": false,
  "code": 422,
  "status": "Unprocessable Entity",
  "message": "La variante Negro / M no tiene existencia suficiente.",
  "data": { "idProductVariant": 101, "currentStock": 1, "requestedQuantity": 2 }
}
```

---

## Nota sobre impuestos

- `price` es siempre **precio base sin IVA**.
- El IVA aplicable se calcula según `typeIva` usando `impuestos_config` como base (ver [config.md](config.md)).
- `discount`, `isr` e `impEsp` son porcentajes aplicados sobre `price`.
