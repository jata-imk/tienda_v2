# Inventario (V2)

Modelo de inventario con **producto base** separado de sus **existencias por variante**
(producto + talla + color), catálogos de tallas/colores e historial de movimientos.

Todos los endpoints requieren:
- Header `Authorization: Bearer <JWT>`
- Usuario con tipo `administrador`

Las respuestas usan el formato estándar `ApiResponse` (`{ ok, code, status, message, data }`).
Los listados (`index` / `query`) soportan los filtros `p[page]`, `p[per_page]`, `f[]`,
`o[column]`, `o[direction]`, `w[column]`, `totalCount` (igual que `products`).

---

## Modelo de datos

| Tabla | Rol |
|---|---|
| `products` | Producto base (datos comerciales y fiscales). Una fila por producto. |
| `categories` | Clasificación de productos. |
| `size_groups` | Grupos de tallas (Adultos, Niños). Catálogo. |
| `sizes` | Tallas por grupo (con `sort_order`). Catálogo. |
| `colors` | Colores reutilizables (`hex_color`). Catálogo. |
| `product_variants` | Existencia real por `producto + talla + color`. Único `id_product+id_size+id_color`. |
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
| `DELETE` | `/api/products/{id}` | Desactivar (status → inactive) |

### Campos del producto base
| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `idCategory` | int | sí | FK → categories |
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

### Ejemplo de alta
```json
{
  "idCategory": 1,
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

`POST` registra un movimiento sobre una variante y actualiza su `stock` de forma atómica.

| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `idProductVariant` | int | sí | FK → product_variants |
| `movementType` | string | sí | entry / sale / adjustment / return / cancel |
| `quantity` | number | sí | Magnitud positiva del movimiento |
| `referenceType` | string | no | Origen (ej. `manual_adjustment`, `sales_note`) |
| `referenceId` | int | no | Documento origen |
| `notes` | string | no | Comentario |
| `idUser` | int | sí | Usuario |

**Efecto sobre el stock:** `entry`, `return`, `cancel` **incrementan**; `sale`, `adjustment`
**disminuyen**. Si el resultado quedara negativo, responde `422`.

```json
{
  "idProductVariant": 6,
  "movementType": "entry",
  "quantity": 5,
  "referenceType": "manual_adjustment",
  "notes": "Entrada manual de mercancia",
  "idUser": 1
}
```

---

## Nota sobre impuestos

- `price` es siempre **precio base sin IVA**.
- El IVA aplicable se calcula según `typeIva` usando `impuestos_config` como base (ver [config.md](config.md)).
- `discount`, `isr` e `impEsp` son porcentajes aplicados sobre `price`.
