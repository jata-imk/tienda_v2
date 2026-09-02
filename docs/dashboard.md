# Dashboard

Métricas agregadas de inventario y ventas en un solo endpoint.

Requiere:
- Header `Authorization: Bearer <JWT>`
- Usuario con tipo `administrador`

---

## POST /api/dashboard/query

Recibe los filtros en un body JSON:

| Campo | Tipo | Default | Descripción |
|---|---|---|---|
| `limit` | int (1-50) | 5 | Registros por ranking |
| `dateFrom` | date | — | Inicio inclusivo del rango de ventas |
| `dateTo` | date | — | Fin inclusivo del rango de ventas (≥ `dateFrom`) |
| `lowStockThreshold` | number | 5 | Existencia considerada baja |

```http
POST /api/dashboard/query
Content-Type: application/json
Authorization: Bearer <JWT>
```

```json
{
  "limit": 5,
  "dateFrom": "2026-07-22",
  "dateTo": "2026-08-20",
  "lowStockThreshold": 5
}
```

## GET /api/dashboard (compatibilidad)

El endpoint original se conserva. Recibe los mismos filtros como query parameters:

| Query param | Tipo | Default | Descripción |
|---|---|---|---|
| `limit` | int (1-50) | 5 | Registros por ranking |
| `dateFrom` | date | — | Inicio del rango de ventas |
| `dateTo` | date | — | Fin del rango de ventas (≥ `dateFrom`) |
| `lowStockThreshold` | number | 5 | Existencia considerada baja |

`limit` aplica a `topProducts`, `lowestStock` y `highestStock`. **No** aplica a
`criticalStockBySize`, que devuelve todas las combinaciones bajo el umbral.

`lowStockThreshold` filtra `criticalStockBySize` y `summary.lowStockCount`.

`dateFrom`/`dateTo` solo afectan a `topProducts`: **todo lo demás es estado actual del
inventario**, no un corte histórico. El rango es **inclusivo en ambos extremos** — una fecha
sin hora se lleva al final del día — y se interpreta en la zona horaria de la aplicación
(`APP_TIMEZONE`, hoy `America/Mexico_City`), la misma con la que se graban los `created_at`.

```
GET /api/dashboard?limit=10&dateFrom=2026-01-01&dateTo=2026-07-21&lowStockThreshold=3
```

### Respuesta

```json
{
  "ok": true, "code": 200, "status": "OK",
  "message": "Dashboard retrieved.",
  "data": {
    "topProducts": [
      { "id": 1, "key": "CAM-001", "name": "Camisa lino caballero", "quantitySold": 42 }
    ],
    "lowestStock": [
      { "id": 3, "key": "FIL-001", "name": "Filipina caballero", "stock": 2 }
    ],
    "highestStock": [
      { "id": 1, "key": "CAM-001", "name": "Camisa lino caballero", "stock": 180 }
    ],
    "criticalStockBySize": [
      { "product": "Filipina caballero", "key": "FIL-001", "size": "G", "stock": 2 },
      { "product": "Camisa bolitas", "key": "CAM-003", "size": "CH", "stock": 3 }
    ],
    "summary": {
      "totalProducts": 4,
      "activeProducts": 4,
      "totalVariants": 22,
      "totalStock": 35,
      "inventoryValue": 12100,
      "inventorySaleValue": 16800,
      "lowStockCount": 1,
      "outOfStockCount": 0
    }
  }
}
```

---

## Origen de cada métrica

| Métrica | Cómo se calcula |
|---|---|
| `topProducts` | `SUM(quantity)` de `inventory_movements` con `movement_type = 'sale'`, agrupado por producto (join `product_variants` → `products`). Hoy es la **única** fuente de ventas: no hay tabla de tickets. |
| `lowestStock` / `highestStock` | `SUM(product_variants.stock)` de variantes **activas** por producto activo, ordenado asc/desc. `lowestStock` excluye los ceros nunca abastecidos; `highestStock` conserva todos los productos activos. |
| `totalProducts` / `activeProducts` | `COUNT` sobre `products`. |
| `totalVariants` / `totalStock` | `COUNT` y `SUM(stock)` de variantes activas. |
| `inventoryValue` | `SUM(product_variants.stock * products.cost)` — valuación a costo. |
| `inventorySaleValue` | `SUM(product_variants.stock * products.price)` — valuación al precio de lista, sin descuentos ni impuestos. |
| `lowStockCount` | Productos activos **con `stockControl = true`**, con inventario inicializado y cuya existencia total ≤ `lowStockThreshold`. |
| `outOfStockCount` | Igual que `lowStockCount` pero con existencia total ≤ 0. Conteo **global**: no depende de `limit`. |
| `criticalStockBySize` | `SUM(product_variants.stock)` agrupado por **producto + talla** (join `sizes`), con `HAVING stock ≤ lowStockThreshold`. Solo productos activos con `stockControl = true`, variantes activas e inventario inicializado. |

`lowStockCount` y `criticalStockBySize` **no son comparables**: el primero cuenta *productos*
agregados, el segundo lista *combinaciones producto-talla*. Un mismo producto puede aparecer
en varias filas de `criticalStockBySize`.

En `criticalStockBySize` los colores de una misma talla se **suman** en una sola fila: la
variante es producto+talla+color, pero el bloque se agrega a nivel talla. Los productos tipo
servicio (sin grupo de tallas ni variantes, p. ej. `SERV-001`) nunca aparecen aquí — el join
interno los descarta.

Para las alertas, una variante tiene el inventario inicializado cuando su existencia actual es
mayor que cero o cuando tiene al menos un movimiento histórico. De esta forma, las combinaciones
creadas automáticamente en cero no se reportan como agotadas, mientras que una combinación que
sí tuvo existencias y llegó a cero continúa apareciendo. Este criterio también se aplica a
`lowestStock`, `lowStockCount` y `outOfStockCount`.

`lowestStock` y `highestStock` son **rankings** cortados por `limit`, no la lista de productos
bajo el umbral: pueden incluir productos por encima del umbral si hay pocos productos, e
incluyen productos sin control de existencias.

`inventoryValue` es la valuación **a costo** e `inventorySaleValue` la valuación al **precio de
lista vigente**. Ninguna lleva moneda: el modelo de datos no la tiene (`products.cost` y
`products.price` no referencian `currencies`, y `company_info` no define una moneda por defecto).

Todo se resuelve en SQL agregado (`DashboardService`); no se cargan colecciones ni se usa el
accessor `Product::totalStock` (provocaría N+1).

Cuando exista el módulo de ventas, `topProducts` deberá migrar de `inventory_movements` a la
tabla de ventas real.
