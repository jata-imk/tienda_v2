# Inventory V2 — Guía de integración para Frontend

Este documento describe **qué cambió en la API** del backend para soportar productos de ropa con
tallas y colores, y cómo consumir los endpoints nuevos/modificados. Es **agnóstico de framework**:
solo contratos HTTP (request/response JSON reales y verificados), reglas de validación y un flujo de
UI sugerido. Úsalo junto con tu IA para implementar los ajustes en el cliente.

> Base URL en desarrollo: `http://127.0.0.1:8000/api`

---

## 1. Resumen del cambio

Antes, cada producto guardaba **una sola talla y una sola existencia** (`products.size` y
`products.stock`). Eso no sirve para ropa, donde un mismo modelo existe en varias tallas y colores.

Ahora el producto se separa en dos niveles:

- **Producto base** (`products`): datos comerciales y fiscales. **Una fila por producto.** Ya no
  guarda talla ni stock.
- **Variantes** (`product_variants`): cada combinación real de **producto + talla + color**, con su
  propia existencia (`stock`) y `sku`.

Además se agregan **catálogos** (`size_groups`, `sizes`, `colors`) y un **historial de movimientos**
de inventario (`inventory_movements`).

La existencia total de un producto es la **suma del stock de sus variantes activas** (`totalStock`).

---

## 2. Convenciones generales de la API

### Autenticación
Todos los endpoints de inventario requieren un usuario tipo `administrador` y el header:

```
Authorization: Bearer <token>
```

Login (sin cambios):

```http
POST /api/login
Content-Type: application/json

{ "userName": "suriel.dzul", "password": "suriel2024" }
```

Respuesta `200`:
```json
{
  "ok": true, "code": 200, "status": "OK", "message": "Login successful",
  "data": { "token": "eyJ...", "companyInfo": { }, "user": { } }
}
```

### Envelope de respuesta estándar
Las respuestas de éxito y los **errores de negocio** usan este sobre (`ApiResponse`):

```json
{ "ok": true, "code": 200, "status": "OK", "message": "...", "data": { } }
```

Los listados envuelven los datos así:
```json
{ "ok": true, "code": 200, "status": "OK", "message": "...",
  "data": { "items": [ ], "totalCount": 0, "summary": [0] } }
```

### ⚠️ Dos formatos de error distintos (importante)
- **Errores de validación** (HTTP `422`): formato Laravel, **sin envelope**:
  ```json
  { "message": "The variants.0.sku has already been taken.",
    "errors": { "variants.0.sku": ["The variants.0.sku has already been taken."] } }
  ```
- **Errores de negocio** (HTTP `401`, `404`, `422` de reglas): **con envelope**:
  ```json
  { "ok": false, "code": 422, "status": "Unprocessable Entity",
    "message": "La existencia no puede quedar negativa.", "data": null }
  ```

El front debe contemplar **ambas formas** al manejar errores.

### Otros detalles
- Todos los campos de payload y respuesta van en **camelCase** (`idSizeGroup`, `codeBar`, `totalStock`).
- Los listados (`index` y `POST /query`) aceptan los filtros `p` (paginación), `f` (campos),
  `o` (orden), `w` (where) y `totalCount` — igual que antes.

---

## 3. ⚠️ Breaking changes respecto al contrato anterior

| Antes (V1) | Ahora (V2) |
|---|---|
| `products` tenía `size` (string) y `stock` (number) planos. | **Eliminados.** El stock vive en `variants[].stock`; el total es `totalStock`. |
| Alta de producto = un objeto plano con `size` y `stock`. | Alta = producto base **+** `idSizeGroup` **+** `variants[]` **+** `initialMovement` (opcional). |
| No existían tallas/colores como catálogo. | Hay que precargar `GET /api/sizes` y `GET /api/colors` para armar la matriz. |
| La respuesta de producto no tenía variantes. | La respuesta incluye `variants[]`, `idSizeGroup`, `sizeGroup` y `totalStock`. |

Si tu UI actual manda `size`/`stock` en el alta o los lee en el detalle, **debe migrarse**.

---

## 4. Catálogos

Tres catálogos con CRUD completo y el mismo patrón:
`GET /api/{recurso}`, `POST /api/{recurso}/query`, `GET /api/{recurso}/{id}`,
`POST /api/{recurso}`, `PUT /api/{recurso}/{id}`, `DELETE /api/{recurso}/{id}` (desactiva, no borra).

### 4.1 Grupos de tallas — `/api/size-groups`
Separan tallas de adulto, niño, etc. Un producto pertenece a un solo grupo.

| Campo | Tipo | Req. | Notas |
|---|---|---|---|
| `name` | string | sí | Ej. `Adultos`, `Niños` |
| `description` | string | no | |
| `status` | string | no | `active` / `inactive` (default `active`) |

### 4.2 Tallas — `/api/sizes`
Cada talla pertenece a un grupo. Para la UI de alta, **filtra por grupo**:

```http
GET /api/sizes?w[id_size_group]=1
```

```json
{ "ok": true, "code": 200, "status": "OK", "message": "Sizes retrieved.",
  "data": { "items": [
    { "id": 1, "idSizeGroup": 1, "sizeGroup": "Adultos", "name": "32",
      "sortOrder": 10, "status": "active", "createdAt": "2026-06-12 11:36:00", "updatedAt": "2026-06-12 11:36:00" }
  ], "totalCount": 14, "summary": [14] } }
```

| Campo | Tipo | Req. | Notas |
|---|---|---|---|
| `idSizeGroup` | int | sí | FK → size group |
| `name` | string | sí | Ej. `32`, `M`, `XL` |
| `sortOrder` | int | no | Orden visual (default 0) |
| `status` | string | no | `active` / `inactive` |

Usa `sortOrder` para ordenar las columnas de la matriz (la API ya ordena por `sortOrder`).

### 4.3 Colores — `/api/colors`

| Campo | Tipo | Req. | Notas |
|---|---|---|---|
| `name` | string | sí | Ej. `Blanco` |
| `hexColor` | string | no | Formato `#RRGGBB`, para mostrar muestra visual |
| `status` | string | no | `active` / `inactive` |

```json
{ "id": 1, "name": "Blanco", "hexColor": "#FFFFFF", "status": "active",
  "createdAt": "2026-06-12 11:36:00", "updatedAt": "2026-06-12 11:36:00" }
```

> Si falta un color, se da de alta con `POST /api/colors` **antes** de seleccionarlo en el alta del
> producto. El alta de colores es una operación de catálogo independiente.

---

## 5. Productos — `/api/products`

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/products` | Listar (incluye `variants[]` y `totalStock`) |
| `POST` | `/api/products/query` | Listar con filtros avanzados |
| `GET` | `/api/products/{id}` | Ver uno con variantes |
| `GET` | `/api/products/{id}/variants` | Solo las variantes (matriz) |
| `POST` | `/api/products` | Crear producto + variantes + movimientos iniciales |
| `PUT` | `/api/products/{id}` | Actualizar **datos base** (no variantes) |
| `DELETE` | `/api/products/{id}` | Desactivar (status → `inactive`) |

### 5.1 Campos del producto base

| Campo | Tipo | Req. | Notas |
|---|---|---|---|
| `idCategory` | int | sí | FK → categoría |
| `idSizeGroup` | int | cond. | FK → grupo de tallas. **Requerido si `stockControl=true`** |
| `key` | string | sí | Clave interna única |
| `name` | string | sí | |
| `description` | string | no | |
| `codeBar` | string | no | Código de barras general |
| `price` | number | sí | Precio base **sin IVA** |
| `cost` | number | sí | |
| `stockControl` | bool | sí | Si maneja existencias |
| `discount` | number | no | % descuento (default 0) |
| `typeIva` | int | sí | 1=general, 2=tasa, 3=cuota, 4=no aplica |
| `rateIva` | number | cond. | Tasa IVA (usar con `typeIva=2`) |
| `quotaIva` | number | cond. | Cuota IVA (usar con `typeIva=3`) |
| `isr` | number | no | % ISR (default 0) |
| `impEsp` | number | no | % impuesto especial (default 0) |
| `status` | string | no | `active` / `inactive` |
| `variants` | array | cond. | **Requerido si `stockControl=true`** (ver 5.2) |
| `initialMovement` | object | no | Movimiento inicial (ver 5.3) |

### 5.2 Campos de cada variante (`variants[]`)

| Campo | Tipo | Req. | Notas |
|---|---|---|---|
| `idSize` | int | sí | FK → talla. **Debe pertenecer al `idSizeGroup` del producto** |
| `idColor` | int | sí | FK → color |
| `sku` | string | sí | **Único** en todo el sistema |
| `codeBar` | string | no | Código de barras de la variante |
| `stock` | number | sí | Existencia inicial (puede ser `0`) |
| `status` | string | no | `active` / `inactive` |

### 5.3 `initialMovement` (opcional)

| Campo | Tipo | Req.* | Notas |
|---|---|---|---|
| `movementType` | string | sí | `entry` para carga inicial |
| `referenceType` | string | no | Ej. `initial_load` |
| `referenceId` | int | no | Documento origen |
| `notes` | string | no | |
| `idUser` | int | sí | Usuario que genera el movimiento |

\* Requeridos solo si envías `initialMovement`. El backend crea **un movimiento por cada variante
con `stock > 0`** (con `previousStock=0`, `newStock=stock`). Si lo omites, no se generan movimientos
(las variantes igual se crean con su stock).

### 5.4 Alta — request (verificado)

```http
POST /api/products
Authorization: Bearer <token>
Content-Type: application/json
```
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
    { "idSize": 4, "idColor": 1, "sku": "CAM-001-38-BLA", "stock": 2 },
    { "idSize": 2, "idColor": 2, "sku": "CAM-001-34-AZM", "stock": 1 },
    { "idSize": 3, "idColor": 2, "sku": "CAM-001-36-AZM", "stock": 0 },
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

### 5.5 Alta — respuesta `201` (verificada)

```json
{
  "ok": true, "code": 201, "status": "Created", "message": "Product created.",
  "data": {
    "id": 1,
    "idCategory": 1,
    "category": "Camisas lino",
    "idSizeGroup": 1,
    "sizeGroup": "Adultos",
    "key": "CAM-001",
    "name": "Camisa lino caballero",
    "description": "Camisa 100% lino manga larga",
    "codeBar": null,
    "price": 800,
    "cost": 600,
    "stockControl": true,
    "totalStock": 11,
    "discount": 0,
    "typeIVA": 1,
    "rateIVA": 16,
    "quotaIVA": null,
    "isr": 0,
    "impEsp": 0,
    "status": "active",
    "variants": [
      { "id": 1, "idProduct": 1, "idSize": 2, "size": "34", "idColor": 1, "color": "Blanco",
        "hexColor": "#FFFFFF", "sku": "CAM-001-34-BLA", "codeBar": "7500000000011", "stock": 3, "status": "active" }
      // ... resto de variantes
    ],
    "createdAt": "2026-06-12 11:36:00",
    "updatedAt": "2026-06-12 11:36:00"
  }
}
```

> Nota: la operación es **transaccional**. Si una variante falla (SKU repetido, talla fuera del
> grupo, combinación duplicada), **no se crea nada** (ni el producto).

### 5.6 Ver variantes de un producto (matriz)

```http
GET /api/products/1/variants
```
Devuelve un listado (`data.items[]`) de variantes con `size`, `color`, `hexColor`, `sku`, `stock`.
Útil para reconstruir la matriz de captura/edición.

### 5.7 Actualizar producto — `PUT /api/products/{id}`
Actualiza **solo los datos base** del producto (nombre, precio, impuestos, `idSizeGroup`, etc.).
**No** crea/edita/borra variantes. Acepta los mismos campos del producto base (todos opcionales).

> Para cambiar existencias de una variante, usa **movimientos** (sección 6). Para agregar/quitar
> combinaciones talla-color todavía **no hay endpoint** (ver sección 8).

### 5.8 Producto sin inventario (`stockControl=false`)
No requiere `idSizeGroup` ni variantes. Envía:
```json
{ "idCategory": 1, "key": "SERV-001", "name": "Ajuste de prenda",
  "price": 150, "cost": 0, "stockControl": false, "typeIva": 1, "variants": [] }
```

---

## 6. Ajuste de existencias — `POST /api/inventory/movements`

Registra **uno o varios** movimientos sobre variantes de un mismo producto y actualiza su
`stock` en una sola operación atómica: si cualquier elemento del lote falla, no se aplica
ninguno. Reemplaza el formato anterior de un solo movimiento por payload — **no hay
compatibilidad hacia atrás**, `movements` siempre es arreglo (de 1 a 200 elementos).

| Campo | Tipo | Req. | Notas |
|---|---|---|---|
| `idProduct` | int | sí | Producto al que pertenecen todas las variantes |
| `idUser` | int | sí | Usuario. **Debe ser el de la sesión activa** (se valida contra el JWT) |
| `referenceType` | string | no | Ej. `manual_adjustment`, `sales_note` |
| `referenceId` | int | no | Documento origen |
| `notes` | string | no | Comentario general, se aplica a cada movimiento |
| `movements[].idProductVariant` | int | sí | Variante afectada. No se repite dentro del lote |
| `movements[].movementType` | string | sí | `entry` / `sale` / `adjustment` / `return` / `cancel` |
| `movements[].quantity` | number | sí | **Magnitud positiva** del movimiento |

**Regla de signo:** `entry`, `return`, `cancel` **suman** stock; `sale`, `adjustment` **restan**.
Si el resultado de cualquier variante quedara negativo, responde `422` (con envelope) y **no se
aplica nada del lote**. Todas las variantes deben pertenecer a `idProduct` y este debe tener
`stockControl=true`.

Request (verificado):
```json
{ "idProduct": 25, "idUser": 1, "referenceType": "manual_adjustment",
  "notes": "Ajuste por conteo físico",
  "movements": [
    { "idProductVariant": 101, "movementType": "adjustment", "quantity": 2 },
    { "idProductVariant": 102, "movementType": "entry", "quantity": 3 }
  ] }
```

Respuesta `201` (verificada) — `data.movements` siempre es arreglo, incluso con un solo
movimiento, y trae `data.totalStock` (suma de variantes **activas** del producto):
```json
{ "ok": true, "code": 201, "status": "Created",
  "message": "Ajustes de inventario registrados correctamente.",
  "data": {
    "movements": [
      { "id": 501, "idProductVariant": 101, "movementType": "adjustment", "quantity": 2,
        "previousStock": 8, "newStock": 6, "referenceType": "manual_adjustment",
        "referenceId": null, "notes": "Ajuste por conteo físico", "idUser": 1,
        "createdAt": "2026-08-12 21:45:00" },
      { "id": 502, "idProductVariant": 102, "movementType": "entry", "quantity": 3,
        "previousStock": 4, "newStock": 7, "referenceType": "manual_adjustment",
        "referenceId": null, "notes": "Ajuste por conteo físico", "idUser": 1,
        "createdAt": "2026-08-12 21:45:00" }
    ],
    "totalStock": 13
  } }
```

---

## 7. Flujo de UI recomendado para el alta

1. **Datos generales**: capturar categoría, clave, nombre, precio, costo, impuestos y `stockControl`.
2. **Grupo de tallas**: si `stockControl=true`, elegir `idSizeGroup` y cargar sus tallas con
   `GET /api/sizes?w[id_size_group]={id}` (ya vienen ordenadas por `sortOrder`).
3. **Colores**: elegir uno o varios de `GET /api/colors` (o dar de alta uno nuevo si falta).
4. **Matriz color × talla**: mostrar una cuadrícula (filas = colores elegidos, columnas = tallas).
   Cada celda es la existencia inicial de esa combinación.
5. **Enviar**: convertir la matriz a `variants[]` y mandar todo en **un solo** `POST /api/products`.

### Mapeo matriz ↔ `variants[]`
Matriz visual:

| Color / Talla | 34 | 36 | 38 |
|---|---:|---:|---:|
| Blanco | 3 | 1 | 2 |
| Azul marino | 1 | 0 | 4 |

Cada celda genera una fila de `variants[]` con `idSize` (de la columna), `idColor` (de la fila),
su `sku` y `stock`. Una celda en `0` es válida (crea la combinación con existencia cero); si no
quieres crear esa combinación, simplemente **no** la incluyas en `variants[]`.

---

## 8. Reglas que el front debe respetar

- `idSize` de cada variante **debe** pertenecer al `idSizeGroup` del producto (si no, error `422`).
- `sku` es **único** globalmente.
- No repetir la combinación `idSize + idColor` dentro del mismo `variants[]`.
- Si `stockControl=false`: enviar `variants: []` y `idSizeGroup` es opcional.
- El `sku` lo define el front (sugerencia: `KEY-TALLA-COLOR`, ej. `CAM-001-34-BLA`).

---

## 9. Aún no disponible (para que no lo asuman)

- **Edición de variantes vía API**: hoy `PUT /api/products/{id}` no agrega/quita/edita variantes.
  Para cambiar existencias usa movimientos (sección 6). Si necesitan editar la matriz completa,
  háganlo saber para habilitar un endpoint.
- **Ventas**: el módulo de ventas aún no existe. El tipo de movimiento `sale` y
  `referenceType=sales_note` están reservados para cuando se implemente.
