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
| `between` (`v: [a, b]`) | se parte en `>= a` y `<= b` |

Los `%` y `_` dentro del texto se escapan, así que no actúan como comodines.
Cualquier `ao` desconocido cae en `=`.

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
| `GET` | `/api/products/{id}/variants` | Variantes del producto (matriz) |
| `POST` | `/api/products` | Crear producto + variantes + movimientos iniciales |
| `PUT` | `/api/products/{id}` | Actualizar datos base del producto |
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

---

## `/api/inventory/movements` — Ajuste de existencias

`POST` registra uno o varios movimientos sobre variantes de **un mismo producto** y actualiza
su `stock` en una sola operación atómica: si cualquier elemento de `movements` falla, no se
aplica ninguno. Sirve tanto para ajustar una sola variante como para un conteo físico completo.

### Campos generales

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
