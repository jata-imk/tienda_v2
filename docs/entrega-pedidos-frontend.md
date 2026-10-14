# Entrega: módulo de Pedidos de compra (backend → frontend)

Guía de integración del módulo de **pedidos de compra a proveedor**. Explica qué se hizo, qué
se puede hacer desde la UI, el flujo normal de un pedido, los endpoints y una propuesta de
pantallas. Está escrita para leerse de corrido, por una persona o por un agente de IA.

| | |
|---|---|
| Rama | `feature/purchase-orders`, ya integrada en `develop` |
| PR | [#2 → `main`](https://github.com/jata-imk/tienda_v2/pull/2) |
| Contrato detallado | [`docs/pedidos.md`](pedidos.md) (fuente de verdad de reglas y payloads) |
| OpenAPI / Postman | `public/docs/openapi.yaml`, `public/docs/collection.json` (grupo **Orders**) |
| Docs relacionados | [`inventario.md`](inventario.md) (Kardex, tipo `purchase`), [`config.md`](config.md) (monedas, logo), [`database.md`](database.md#pedidos-de-compra) (tablas) |

> **Para agentes de IA.** Si hay conflicto entre este documento y el código, manda el código.
> Fuente de verdad del contrato: `docs/pedidos.md` y los FormRequest en `app/Http/Requests/Order/`.
> Fuente de verdad de la salida: los Resources en `app/Http/Resources/Order/`.
> Reglas de negocio: `app/Services/OrderService.php`.
> Ejemplos ejecutables: los tests en `tests/Feature/Order*Test.php`, que cubren cada endpoint y cada error.

---

## 1. Resumen en 30 segundos

- Un **pedido** se arma con variantes del catálogo (producto + talla + color), cantidad y costo.
- Avanza por estatus: `draft` → `confirmed` → `partially_received` → `received`, o termina en
  `cancelled`.
- **Recibir mercancía sube el stock** de las variantes con un tipo de movimiento nuevo,
  `purchase` ("Compra"). Ese movimiento aparece en el Kardex ligado a su pedido.
- Cada pedido tiene **folio** `PED-0000001`, historial de estatus y un **PDF** descargable.
- **Sin impuestos** y **sin catálogo de proveedores**: el proveedor es texto libre en el pedido.
- La moneda es opcional. Por defecto es la moneda base de la empresa (MXN), y el tipo de cambio
  se congela al confirmar.

---

## 2. Qué cambia para el frontend (checklist)

| Prioridad | Acción |
|---|---|
| **Alta** | Nueva sección **Pedidos de compra**: listado, alta/edición, detalle, recepción, historial y PDF (ver §8). |
| **Alta** | En el **Kardex**, agregar la etiqueta del tipo nuevo `purchase` → "Compra". Hoy llegaría como valor desconocido. |
| Media | En el Kardex, usar `idOrder` / `orderFolio` para enlazar al pedido (§7). |
| Media | Menú por rol: **Almacén** ve Pedidos pero solo puede consultar y recibir. **Vendedor** no ve el menú (§3). |
| Baja | Los formularios de movimientos manuales no deben ofrecer `purchase` ni `referenceType = "order_detail"`: el backend los rechaza con 422. |

Ningún endpoint existente cambió de contrato. Solo hay campos nuevos (`idOrder`, `orderFolio` en
el Kardex) y un valor nuevo de `movementType`.

---

## 3. Permisos por rol

Se resuelven con `user.roleCode` (de la respuesta de login). Detalle en
[`entrega-roles-seeders-frontend.md`](entrega-roles-seeders-frontend.md#2-matriz-de-acceso-vigente).

| Acción | `administrator` | `warehouse` | `seller` |
|---|:-:|:-:|:-:|
| Ver listado, detalle, historial y PDF | ✅ | ✅ | ❌ |
| Recibir mercancía | ✅ | ✅ | ❌ |
| Crear, editar, confirmar y cancelar | ✅ | ❌ | ❌ |

Sin permiso el backend responde `403`. Oculta los botones según el rol, pero maneja el 403 de
todas formas.

---

## 4. Ciclo de vida y qué botones mostrar

```
draft ──confirm──▶ confirmed ──receive──▶ partially_received ──receive──▶ received
  │                    │  └───────────receive (todo)──────────────────────────▲
  └──cancel──▶ cancelled ◀──cancel──┘
```

| `status` | Etiqueta sugerida | Editable | Botones (admin) | Botones (almacén) |
|---|---|:-:|---|---|
| `draft` | Borrador | ✅ | Editar, Confirmar, Cancelar, PDF | PDF |
| `confirmed` | Confirmado | ❌ | Recibir, Cancelar, PDF | Recibir, PDF |
| `partially_received` | Recibido parcialmente | ❌ | Recibir, PDF | Recibir, PDF |
| `received` | Recibido | ❌ | PDF | PDF |
| `cancelled` | Cancelado | ❌ | PDF | PDF |

Reglas que conviene reflejar en la UI (el backend las valida igual):
- Solo un **borrador** se edita. Confirmar congela las líneas y el tipo de cambio.
- **Confirmar no mueve inventario**. Solo **recibir** lo mueve.
- Un pedido con recepciones **nunca se cancela**: `partially_received` solo puede terminar en `received`.
- `received` y `cancelled` son finales.

---

## 5. Flujo normal de un pedido (secuencia de llamadas)

Todas las rutas van bajo `/api`, con `Authorization: Bearer <JWT>` y llaves en **camelCase**.
No se manda `idUser`: el usuario se toma del token.

### 5.1 Armar el pedido (admin): `POST /api/orders`

Para elegir variantes reutiliza lo que ya existe: `POST /api/products/query` y luego
`POST /api/products/{id}/variants/query`. Las variantes llegan ordenadas por color y talla, ideal
para una matriz talla × color.

```json
{
  "supplierName": "Textiles Peninsulares S.A. de C.V.",
  "supplierDetails": "Calle 42 #300, Col. Industrial\n97288 Mérida, Yucatán\nRFC TPE990101XY2",
  "dateExpected": "2026-11-05",
  "notes": "Entregar de 9 a 14 h.",
  "details": [
    { "idProductVariant": 1, "quantityOrdered": 4 },
    { "idProductVariant": 2, "quantityOrdered": 6, "unitCost": 550, "notes": "Bordado" }
  ]
}
```

- Omitidos → defaults:
  - `idCurrency`: moneda base (MXN).
  - `dateOrder`: hoy.
  - `unitCost`: `products.cost` convertido a la moneda del pedido.
- Todo lo demás es opcional, salvo `details` (1 a 200 líneas, **sin repetir variante**).
- Responde `201` con el pedido completo (§6.2). El estatus es `draft` y el folio `PED-000000n`.

### 5.2 Ajustar mientras es borrador (admin): `PUT /api/orders/{id}`

- Envía solo lo que cambia. Un `null` limpia el campo.
- Si envías `details`, **reemplaza todas las líneas**. Se identifican por `idProductVariant`: las
  que siguen conservan su `id`, las nuevas se crean y las ausentes se borran.
- Los totales los recalcula el backend; no los mandes.

### 5.3 Confirmar (admin): `POST /api/orders/{id}/confirm`

Body opcional `{ "notes": "Enviado al proveedor" }`, que se guarda en el historial. A partir de
aquí el pedido no se edita. Es buen momento para descargar el PDF y mandarlo al proveedor.

### 5.4 Recibir mercancía (almacén o admin): `POST /api/orders/{id}/receive`

```json
{
  "notes": "Remesa 1",
  "lines": [
    { "detailId": 11, "quantity": 4 },
    { "detailId": 12, "quantity": 2 }
  ]
}
```

- `detailId` es el `id` de la **línea del pedido** (`details[].id`), no el de la variante.
- `quantity` ≤ `quantityPending` de esa línea. Se puede recibir en varias remesas.
- Es **todo o nada**: si una línea falla, no se aplica ninguna.
- La respuesta trae el pedido actualizado y los movimientos generados (§6.3). El estatus pasa a
  `partially_received`, o a `received` si ya no queda nada pendiente.

### 5.5 Consultar

- Historial: `GET /api/orders/{id}/history`.
- PDF: `GET /api/orders/{id}/pdf` (§9).

---

## 6. Endpoints y payloads

| Método | Ruta | Rol | Respuesta OK |
|---|---|---|---|
| `POST` | `/api/orders/query` | admin, almacén | `200` grid `{ items, totalCount, summary }` |
| `GET` | `/api/orders/{id}` | admin, almacén | `200` pedido con `details` |
| `POST` | `/api/orders` | admin | `201` pedido |
| `PUT` | `/api/orders/{id}` | admin | `200` pedido |
| `POST` | `/api/orders/{id}/confirm` | admin | `200` pedido |
| `POST` | `/api/orders/{id}/cancel` | admin | `200` pedido |
| `POST` | `/api/orders/{id}/receive` | admin, almacén | `200` `{ order, movements }` |
| `GET` | `/api/orders/{id}/history` | admin, almacén | `200` arreglo de transiciones |
| `GET` | `/api/orders/{id}/pdf` | admin, almacén | `200` **binario** `application/pdf` |

### 6.1 Listado: `POST /api/orders/query`

Mismo contrato de grid que el resto de la API (`p`, `f`, `o`, `w`, `totalCount`; DevExtreme
`filterRow`/`filterBuilder`). Sin `o`, los más recientes primero. **No incluye `details`.**

| Filtro `w` | Uso |
|---|---|
| `status` | `==` o `in` con los valores del §4 |
| `search` | Folio (`PED-0000012`, `PED-12` o `12`), notas, proveedor, usuario |
| `idProduct` / `sku` | Pedidos que contienen ese producto o variante |
| `dateOrder`, `dateExpected`, `idCurrency`, `folio` | Columnas normales (`between`, `>=`, …) |

`o[column]` debe ser una columna real de `orders` (`folio`, `dateOrder`, `totalAmount`…); si no,
responde `422`.

### 6.2 Forma del pedido (`OrderResource`)

```json
{
  "id": 1, "key": "PED", "folio": 1, "folioLabel": "PED-0000001",
  "status": "confirmed",
  "idCurrency": 1, "currencyCode": "MXN", "exchangeRate": 1,
  "totalItems": 10, "totalAmount": 6100,
  "supplierName": "Textiles Peninsulares", "supplierDetails": null, "shippingAddress": null,
  "notes": null, "dateOrder": "2026-10-15", "dateExpected": "2026-11-05", "dateReceived": null,
  "idUser": 1, "userName": "admin", "createdAt": "2026-10-15 10:00:00", "updatedAt": null,
  "details": [
    {
      "id": 11, "idOrder": 1, "idProductVariant": 1,
      "quantityOrdered": 4, "quantityReceived": 0, "quantityPending": 4,
      "unitCost": 600, "subtotal": 2400, "notes": null,
      "idProduct": 1, "productKey": "CAM-001", "productName": "Camisa lino caballero",
      "sku": "CAM-001-34-BLA", "size": "34", "color": "Blanco"
    }
  ]
}
```

- Muestra `folioLabel`; `folio` es el número para ordenar o filtrar.
- `exchangeRate` es la **tasa efectiva**: en borrador sigue la de la moneda, y al confirmar queda fija.
- `totalItems` = suma de piezas; `totalAmount` = suma de subtotales (sin IVA).
- `quantityPending` ya viene calculado: úsalo para la pantalla de recepción.

### 6.3 Respuesta de recepción

```json
{
  "order": { "...": "OrderResource completo, como en 6.2" },
  "movements": [
    { "id": 50, "idProductVariant": 1, "movementType": "purchase", "quantity": 4,
      "previousStock": 3, "newStock": 7, "referenceType": "order_detail", "referenceId": 11 }
  ]
}
```

### 6.4 Errores

| Código | Formato | Cuándo | Qué mostrar |
|---|---|---|---|
| `422` validación | `{ "message", "errors": { "campo": ["..."] } }` (formato estándar de Laravel) | Campos faltantes o inválidos, variante repetida, `dateExpected < dateOrder` | Marcar el campo, p. ej. `details.1.idProductVariant` |
| `422` negocio | `ApiResponse` `{ ok:false, message, data }` | Variante inactiva o sin control de existencias; recibir más de lo pendiente (`data` trae `detailId`, `quantityOrdered`, `quantityReceived`, `requestedQuantity`); línea de otro pedido | `message` + resaltar la línea de `data.detailId` / `data.idProductVariant` |
| `409` | `ApiResponse` | Transición inválida: editar fuera de borrador, cancelar con recepciones, recibir un borrador o un pedido terminado | `message` y recargar el pedido (alguien más lo cambió) |
| `403` | `ApiResponse` | Rol sin permiso | Ocultar la acción |
| `404` | `ApiResponse` | Pedido inexistente | — |

---

## 7. Kardex (inventario)

Los cambios están en `POST /api/inventory/movements/query`
([`inventario.md`](inventario.md#apiinventorymovements--historial-kardex-y-ajuste-de-existencias)):

- `movementType` puede ser **`purchase`**: entrada por recepción de pedido, que **suma** stock.
- Cada movimiento trae `idOrder` y `orderFolio` (`"PED-0000001"`). En movimientos que no vienen
  de un pedido van en `null`. Úsalos para un link "Ver pedido".
- Nuevo filtro `w` → `idOrder`, y `search` también encuentra por folio.
- `purchase` y `referenceType: "order_detail"` **no se pueden capturar a mano**: el
  `POST /api/inventory/movements` y el `initialMovement` de productos los rechazan con 422.
- El dashboard no cambia: `topProducts` solo cuenta ventas (`sale`).

---

## 8. Pantallas recomendadas

1. **Listado de pedidos.** Grid DevExtreme contra `POST /orders/query`.
   - Columnas: `folioLabel`, `status` (badge), `supplierName`, `dateOrder`, `dateExpected`,
     `totalItems`, `totalAmount` + `currencyCode`, `userName`.
   - Filtro rápido de estatus y búsqueda con `search`.
2. **Alta y edición (solo admin, solo borrador).**
   - Encabezado: proveedor, detalles del proveedor (multilínea), fecha de entrega, dirección de
     envío (multilínea; vacía = la de la empresa), moneda y tipo de cambio (solo si la moneda no
     es la base), notas.
   - Líneas: elegir producto y mostrar la **matriz talla × color** de sus variantes activas para
     capturar cantidades. Envía una línea por celda con cantidad > 0. El costo es editable y por
     default viene el del producto.
   - Al guardar, `POST` o `PUT` con **todas** las líneas. Muestra los totales que regresa el
     backend; no los calcules tú.
3. **Detalle.**
   - Encabezado, líneas agrupadas por producto + color (igual que el PDF) y columnas pedido,
     recibido y pendiente.
   - Botones según el §4, más una pestaña de **Historial** (`GET /history`).
4. **Recepción (almacén o admin).**
   - Lista las líneas con `quantityPending > 0` y un campo "recibir" prellenado con lo pendiente
     y editable. Agrega un campo de notas (p. ej. número de remesa).
   - Manda solo las líneas con cantidad > 0. Al responder, refresca con `data.order`.
5. **PDF.** Botón "Ver" o "Descargar" (§9).

Variantes inactivas o de productos sin control de existencias no se pueden pedir (422). Filtra
`status = active` y `stockControl = true` al buscar productos.

---

## 9. PDF

`GET /api/orders/{id}/pdf` devuelve el archivo, no JSON. Por defecto va `inline`; con
`?download=1` se descarga como `PED-0000001.pdf`. Como lleva JWT, pídelo con `fetch` y ábrelo
como blob:

```ts
const res = await fetch(`${API}/orders/${id}/pdf`, { headers: { Authorization: `Bearer ${token}` } });
if (!res.ok) throw await res.json();            // 403 / 404 vienen en JSON
window.open(URL.createObjectURL(await res.blob()));
```

Contenido del PDF:
- Encabezado: logo y datos de la empresa (de `company-info`), proveedor e información del pedido.
- Dirección de envío.
- Líneas **agrupadas por producto + color, con una fila por talla**.
- Totales y paginación.
- Marca de agua **BORRADOR** o **CANCELADO** según el estatus.

Ver la tabla "qué alimenta cada bloque" en [`pedidos.md`](pedidos.md#pdf-get-apiordersidpdf).

---

## 10. Base de datos y migraciones

Detalle de columnas en [`database.md`](database.md#pedidos-de-compra).

| Migración | Qué hace |
|---|---|
| `2026_10_15_000001_create_orders_table` | Encabezado: folio único por serie, estatus, moneda y tasa, totales, proveedor (texto), envío y fechas |
| `2026_10_15_000002_create_order_details_table` | Líneas: una por variante y pedido; `quantity_ordered`/`quantity_received`. En MariaDB, CHECK `0 ≤ recibido ≤ pedido` |
| `2026_10_15_000003_create_order_status_history_table` | Una fila por transición (quién, cuándo, nota) |
| `2026_10_15_000004_add_purchase_movement_type` | `inventory_movements.movement_type` + `purchase`; índice `(reference_type, reference_id)` |
| `2026_10_15_000005_create_document_sequences_table` | Contador de folios por serie (`PED`); evita folios repetidos y deadlocks con altas simultáneas |

Para levantar el backend local: `php artisan migrate` (o `migrate:fresh --seed` en desarrollo).
`CompanySeeder` carga el logo de la empresa demo si no hay uno.

---

## 11. Commits para profundizar

Los commits de pedidos van de `5cf7201` a `92d34da`. Cada uno trae sus tests.

| Commit | Qué contiene |
|---|---|
| `5cf7201` | Dependencia `barryvdh/laravel-dompdf` (PDF) |
| `8dd86cb` | Migraciones de pedidos, modelos, enum `OrderStatus` y tipo `purchase` |
| `44dc7af` | Módulo completo: Service, Requests, Resources, Controller, rutas, Kardex y PDF, con sus tests |
| `973911b` | `docs/pedidos.md` y Scribe regenerado |
| `ec4d604` | Folio con contador bloqueado (fix de deadlocks detectado probando concurrencia en MariaDB) |
| `de5a2ad` | Folio de 7 dígitos `PED-0000001` |
| `ce01b18` | Logo de la empresa demo en el seeder |
| `7fa77d5` | PDF: columna Talla, producto + color una sola vez |
| `92d34da` | Documentación general puesta al día |

---

## 12. Fuera de alcance (por ahora)

- **Proveedores como catálogo:** hoy son texto libre (`supplierName`, `supplierDetails`).
- **Impuestos en compras:** no se calcula IVA.
- **Revertir una recepción:** no existe. Una corrección se hace con un movimiento `adjustment`.
- Recibir **no** actualiza `products.cost`.
