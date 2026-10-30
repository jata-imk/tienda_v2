# Pedidos de compra (`/api/orders`)

Pedidos a proveedor armados desde el catálogo (variantes). El pedido controla su estatus y, al
recibir la mercancía, **suma existencias** con movimientos de inventario tipo `purchase`.

No hay entidad proveedor: los datos del proveedor son texto libre en el pedido
(`supplierName`, `supplierDetails`). Las compras **no manejan impuestos**: el total es la suma
de `cantidad × costo` de las líneas.

Las respuestas usan el formato estándar `ApiResponse` (`{ ok, code, status, message, data }`),
salvo el PDF.

---

## Acceso por rol

| Endpoint | Administrador | Almacén | Vendedor |
|---|:-:|:-:|:-:|
| `POST /orders/query`, `GET /orders/{id}`, `GET /orders/{id}/history`, `GET /orders/{id}/pdf` | Sí | Sí | No |
| `POST /orders/{id}/receive` | Sí | Sí | No |
| `POST /orders`, `PUT /orders/{id}`, `POST /orders/{id}/confirm`, `POST /orders/{id}/cancel` | Sí | No | No |

Sin permiso → `403`. El usuario del pedido, de cada transición y de cada movimiento es **el de
la sesión** (JWT); no se envía `idUser`.

---

## Máquina de estados

```
draft ──confirm──▶ confirmed ──receive──▶ partially_received ──receive──▶ received
  │                    │  └──────────receive (todo)──────────────────────────▲
  └──cancel──▶ cancelled ◀──cancel──┘
```

| Desde | Hacia | Cómo | Efecto |
|---|---|---|---|
| — | `draft` | `POST /orders` | Folio consecutivo con 7 dígitos (`PED-0000001`) (si el alta falla, el folio no se consume). Editable. No toca stock. |
| `draft` | `confirmed` | `POST /orders/{id}/confirm` | Bloquea las líneas y congela el tipo de cambio. No toca stock. |
| `draft` / `confirmed` | `cancelled` | `POST /orders/{id}/cancel` | Solo si no hay recepciones. Final. |
| `confirmed` / `partially_received` | `partially_received` | `POST /orders/{id}/receive` | Quedan líneas con pendiente. |
| `confirmed` / `partially_received` | `received` | `POST /orders/{id}/receive` | Todas las líneas completas. Llena `dateReceived`. Final. |

- `partially_received` **nunca se cancela** (409). Se completa con recepciones posteriores.
- `received` y `cancelled` son finales: cualquier transición responde `409`.
- Cada transición queda en `GET /orders/{id}/history` (incluida la creación, `null → draft`).
  Una recepción que deja el pedido en `partially_received` cuando ya estaba así no es transición
  y no escribe historial; el rastro queda en los movimientos de inventario.

---

## Moneda, tipo de cambio y costo

- **Moneda base** = `company_info.id_currency` (MXN en el seeder); si la empresa no la tiene
  configurada, `MXN`. Sin `idCurrency`, el pedido toma la base.
- `exchangeRate` = cuántas unidades de la moneda base vale 1 unidad de la moneda del pedido (misma
  convención que `currencies.exchangeRate`, ver [config.md](config.md)). La moneda base siempre vale `1`.
- **En `draft`** la tasa sigue a `currencies.exchange_rate` salvo que se capture una explícita.
  **Al confirmar se congela**: cambiar la tasa de la moneda después ya no afecta al pedido. La
  respuesta siempre trae la tasa efectiva.
- Cambiar `idCurrency` en draft sin enviar `exchangeRate` descarta la tasa capturada antes.
- **`unitCost`** es opcional: si no se envía, toma `products.cost` convertido a la moneda del
  pedido (`cost / exchangeRate`, 2 decimales). El costo de las líneas ya capturadas **no** se
  recalcula si después cambia la moneda o la tasa. Recibir **no** modifica `products.cost`.
- `subtotal = round(quantityOrdered × unitCost, 2)`; `totalAmount = Σ subtotal`;
  `totalItems = Σ quantityOrdered` (piezas). Los recalcula el backend en cada alta/edición.

---

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/orders/query` | Listar con filtros de grid |
| `GET` | `/api/orders/{id}` | Ver uno con sus líneas |
| `POST` | `/api/orders` | Crear (draft) |
| `PUT` | `/api/orders/{id}` | Editar (solo draft) |
| `POST` | `/api/orders/{id}/confirm` | draft → confirmed |
| `POST` | `/api/orders/{id}/receive` | Recibir mercancía |
| `POST` | `/api/orders/{id}/cancel` | Cancelar |
| `GET` | `/api/orders/{id}/history` | Historial de estatus |
| `GET` | `/api/orders/{id}/pdf` | PDF del pedido |

### `POST /api/orders/query`

Mismo contrato de grid que el resto (`p`, `f`, `o`, `w`, `totalCount`; ver
[inventario.md](inventario.md)). Sin `o`, los más recientes primero. El listado **no** incluye
las líneas (`details`); para eso, `GET /orders/{id}`.

Campos virtuales en `w`:

| Campo | Se resuelve como |
|---|---|
| `search` | Folio (`PED-0000012`, `PED-12` o `12`), `notes`, `supplierName`, usuario |
| `idProduct` | Pedidos con alguna línea de ese producto |
| `sku` | Pedidos con alguna línea de esa variante |

Columnas útiles: `status`, `idCurrency`, `dateOrder`, `dateExpected`, `folio`.

### `POST /api/orders`

| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `idCurrency` | int | no | Moneda. Default: la base |
| `exchangeRate` | number | no | Tasa explícita (> 0). Ignorada si la moneda es la base |
| `dateOrder` | date | no | Default: hoy |
| `dateExpected` | date | no | Fecha de entrega esperada (≥ `dateOrder`) |
| `supplierName` | string | no | Proveedor (máx. 255) |
| `supplierDetails` | string | no | Dirección, RFC, contacto (multilínea, máx. 2000) |
| `shippingAddress` | string | no | Dirección de envío (multilínea). Solo referencia: **no se imprime en el PDF** |
| `notes` | string | no | Comentarios |
| `details` | array | sí | 1 a 200 líneas |
| `details[].idProductVariant` | int | sí | Variante. **No se repite** dentro del pedido |
| `details[].quantityOrdered` | number | sí | > 0, hasta 3 decimales |
| `details[].unitCost` | number | no | Costo unitario en la moneda del pedido. Igual en todas las tallas del mismo producto + color |
| `details[].notes` | string | no | Comentario de la línea |

Reglas de negocio (`422`, no se crea nada):
- La variante y su producto deben estar `active`.
- El producto debe tener `stockControl = true` (los servicios sin existencias no se compran por aquí).
- La moneda debe estar `active`.
- Todas las tallas de un mismo **producto + color** llevan el mismo costo unitario (el explícito o el
  tomado de `products.cost`). Si no, `422` con `data.sku`, `data.unitCost` y `data.expectedUnitCost`.
  Colores distintos sí pueden tener costos distintos. Aplica igual en `PUT`.

```json
// Request
{
  "supplierName": "Textiles Peninsulares",
  "dateExpected": "2026-11-05",
  "details": [
    { "idProductVariant": 1, "quantityOrdered": 10 },
    { "idProductVariant": 2, "quantityOrdered": 5, "unitCost": 550 }
  ]
}
```

```json
// Response 201
{
  "ok": true, "code": 201, "status": "Created", "message": "Order created.",
  "data": {
    "id": 1, "key": "PED", "folio": 1, "folioLabel": "PED-0000001", "status": "draft",
    "idCurrency": 1, "currencyCode": "MXN", "exchangeRate": 1,
    "totalItems": 15, "totalAmount": 8750,
    "supplierName": "Textiles Peninsulares", "supplierDetails": null, "shippingAddress": null,
    "notes": null, "dateOrder": "2026-10-15", "dateExpected": "2026-11-05", "dateReceived": null,
    "idUser": 1, "userName": "admin", "createdAt": "2026-10-15 10:00:00", "updatedAt": null,
    "details": [
      {
        "id": 1, "idOrder": 1, "idProductVariant": 1,
        "quantityOrdered": 10, "quantityReceived": 0, "quantityPending": 10,
        "unitCost": 600, "subtotal": 6000, "notes": null,
        "idProduct": 1, "productKey": "CAM-001", "productName": "Camisa lino caballero",
        "sku": "CAM-001-34-BLA", "size": "34", "color": "Blanco"
      }
    ]
  }
}
```

### `PUT /api/orders/{id}`

Solo en `draft` (si no, `409`). Mismos campos que el alta, todos opcionales: los omitidos no se
tocan y un `null` limpia el campo (salvo `dateOrder`, que no se puede vaciar).

Si se envía `details`, **reemplaza el conjunto completo** de líneas, identificadas por
`idProductVariant`: la variante que ya estaba conserva el `id` de su línea, las nuevas se crean y
las que no vienen se borran.

### `POST /api/orders/{id}/confirm` · `POST /api/orders/{id}/cancel`

Body opcional `{ "notes": "..." }` que se guarda en el historial (p. ej. el motivo de la
cancelación). Confirmar exige al menos una línea.

### `POST /api/orders/{id}/receive`

Solo en `confirmed` o `partially_received` (si no, `409`).

| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `notes` | string | no | Comentario de la recepción (p. ej. "Remesa 1") |
| `lines` | array | sí | 1 a 200 |
| `lines[].detailId` | int | sí | `id` de la línea del pedido. No se repite |
| `lines[].quantity` | number | sí | > 0 y ≤ pendiente de la línea |

Por cada línea recibida:
- `quantityReceived` aumenta.
- El stock de la variante sube con un movimiento **`purchase`**, `referenceType = "order_detail"`,
  `referenceId` = `detailId`, nota `"Recepción PED-0000001. <notes>"`.

Es **atómico**: si una línea excede su pendiente, no pertenece al pedido o el inventario rechaza
el movimiento, responde `422` y no se aplica nada de la recepción.

```json
// Request
{ "notes": "Remesa 1", "lines": [ { "detailId": 1, "quantity": 4 } ] }
```

```json
// Response 200
{
  "ok": true, "code": 200, "status": "OK", "message": "Order received.",
  "data": {
    "order": { "id": 1, "status": "partially_received", "details": [ { "id": 1, "quantityReceived": 4, "quantityPending": 6 } ] },
    "movements": [
      { "id": 10, "idProductVariant": 1, "movementType": "purchase", "quantity": 4,
        "previousStock": 3, "newStock": 7, "referenceType": "order_detail", "referenceId": 1 }
    ]
  }
}
```

```json
// 422: cantidad mayor a lo pendiente
{
  "ok": false, "code": 422, "status": "Unprocessable Entity",
  "message": "La cantidad recibida excede lo pendiente de la linea.",
  "data": { "detailId": 1, "quantityOrdered": 10, "quantityReceived": 7, "requestedQuantity": 5 }
}
```

### `GET /api/orders/{id}/history`

```json
{
  "data": [
    { "id": 1, "idOrder": 1, "previousStatus": null, "newStatus": "draft", "notes": null, "idUser": 1, "userName": "admin", "createdAt": "..." },
    { "id": 2, "idOrder": 1, "previousStatus": "draft", "newStatus": "confirmed", "notes": null, "idUser": 1, "userName": "admin", "createdAt": "..." }
  ]
}
```

---

## Trazabilidad en el Kardex

Las entradas por compra se distinguen de las manuales por **tipo** y por **referencia**:

- `movementType = "purchase"`: exclusivo de las recepciones. `POST /api/inventory/movements` y el
  `initialMovement` de productos/variantes lo rechazan, así que no se puede capturar a mano.
- `referenceType = "order_detail"` + `referenceId` = línea del pedido. El `referenceType` también
  está reservado: el endpoint de movimientos manuales lo rechaza.
- `POST /api/inventory/movements/query` devuelve `idOrder` y `orderFolio` en cada movimiento de
  compra, acepta `w` con `idOrder` y busca por folio en `search`.

> **Frontend:** el grid del Kardex necesita una etiqueta para el tipo nuevo `purchase` (p. ej.
> "Compra"). El dashboard no se ve afectado: `topProducts` solo cuenta `sale`.

---

## PDF (`GET /api/orders/{id}/pdf`)

Devuelve `application/pdf` (no JSON). Por defecto `inline`; con `?download=1` como descarga
`PED-0000001.pdf`. Disponible en cualquier estatus; en `draft` lleva marca de agua **BORRADOR** y en
`cancelled` **CANCELADO**. Si el pedido no existe responde el JSON `404` estándar.

Desde el frontend se pide con el JWT y se abre como blob:

```ts
const res = await fetch(`${API}/orders/${id}/pdf`, { headers: { Authorization: `Bearer ${token}` } });
window.open(URL.createObjectURL(await res.blob()));
```

Qué alimenta cada bloque (tamaño carta, generado con dompdf):

| Bloque | Origen |
|---|---|
| Emisor (izquierda) | `company_info`: razón social, dirección en **una línea** (calle y número, colonia, CP y ciudad) y RFC |
| Logo (derecha) | `company_info.logo`; sin logo la celda queda vacía |
| Proveedor | `supplierName` + `supplierDetails` |
| Información | Folio, fecha emisión (`dateOrder`), `dateExpected`, moneda (+ T.C. si no es la base), estatus |
| Notas | `notes` (el renglón solo aparece si hay notas) |
| Líneas | **Un renglón por producto + color**: Producto/Descripción (clave, nombre · color y las notas de línea como `Talla 34: …`), **Tallas** (matriz Talla / Cant. en el orden de `sizes.sortOrder`, partida cada 6 tallas — `OrderPdfService::SIZES_PER_ROW`), cantidad total, precio unitario y monto neto. Con recepciones se agregan la columna **Recibido** y el renglón `Recib.` en la matriz. Si un pedido anterior a la regla de costo único trae costos distintos en el grupo, el precio sale como `Varios` y el monto es la suma real |
| Totales | Total de piezas y total neto en la moneda del pedido |
| Pie | `PED-0000001 · Página X de Y` |

---

## Errores

| Código | Cuándo |
|---|---|
| `403` | Rol sin acceso |
| `404` | Pedido inexistente |
| `409` | Transición inválida: editar/confirmar fuera de draft, recibir en draft/received/cancelled, cancelar un pedido con recepciones o ya finalizado |
| `422` | Validación del request, variante inactiva o sin control de existencias, moneda inactiva, costo distinto entre tallas del mismo producto + color, recepción que excede lo pendiente o línea de otro pedido |

---

## Modelo de datos

Ver [database.md](database.md#pedidos-de-compra): `orders`, `order_details`, `order_status_history`
y `document_sequences` (contador de folios).
