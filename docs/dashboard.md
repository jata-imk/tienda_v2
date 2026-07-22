# Dashboard

Métricas agregadas de inventario y ventas en un solo endpoint.

Requiere:
- Header `Authorization: Bearer <JWT>`
- Usuario con tipo `administrador`

---

## GET /api/dashboard

| Query param | Tipo | Default | Descripción |
|---|---|---|---|
| `limit` | int (1-50) | 5 | Registros por ranking |
| `dateFrom` | date | — | Inicio del rango de ventas |
| `dateTo` | date | — | Fin del rango de ventas (≥ `dateFrom`) |
| `lowStockThreshold` | number | 5 | Existencia considerada baja |

`limit` aplica a `topProducts`, `lowestStock` y `highestStock`.
`dateFrom`/`dateTo` solo afectan a `topProducts`.

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
    "summary": {
      "totalProducts": 4,
      "activeProducts": 4,
      "totalVariants": 22,
      "totalStock": 35,
      "inventoryValue": 12100,
      "lowStockCount": 1
    }
  }
}
```

---

## Origen de cada métrica

| Métrica | Cómo se calcula |
|---|---|
| `topProducts` | `SUM(quantity)` de `inventory_movements` con `movement_type = 'sale'`, agrupado por producto (join `product_variants` → `products`). Hoy es la **única** fuente de ventas: no hay tabla de tickets. |
| `lowestStock` / `highestStock` | `SUM(product_variants.stock)` de variantes **activas** por producto activo, ordenado asc/desc. |
| `totalProducts` / `activeProducts` | `COUNT` sobre `products`. |
| `totalVariants` / `totalStock` | `COUNT` y `SUM(stock)` de variantes activas. |
| `inventoryValue` | `SUM(product_variants.stock * products.cost)` — valuación a costo. |
| `lowStockCount` | Productos activos **con `stockControl = true`** cuya existencia total ≤ `lowStockThreshold`. |

Todo se resuelve en SQL agregado (`DashboardService`); no se cargan colecciones ni se usa el
accessor `Product::totalStock` (provocaría N+1).

Cuando exista el módulo de ventas, `topProducts` deberá migrar de `inventory_movements` a la
tabla de ventas real.
