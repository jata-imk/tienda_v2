# Entrega: baja de productos, orden de variantes y ordenamiento de grids

Resumen de los cambios de backend de esta ronda y lo que conviene revisar del lado del
frontend. Nada de esto rompe una petición que ya funcionaba; lo que cambia es el
comportamiento de la baja de productos, el orden en que llegan las variantes, y dos errores
que antes eran 500 y ahora son 422.

Al final hay una [tabla de commits](#8-commits-para-profundizar) por si quieres ver el detalle
de cada cambio, con sus tests.

---

## 1. Dar de baja un producto ahora da de baja sus variantes

**El bug reportado.** `DELETE /api/products/{id}` ponía el producto en `inactive` pero dejaba
todas sus variantes en `active`, así que seguían contando como vigentes.

**Ahora cascadea**, y por las dos vías por las que cambia el status:

| Acción | Efecto en las variantes |
|---|---|
| `DELETE /api/products/{id}` | todas pasan a `inactive` |
| `PUT /api/products/{id}` con `{"status": "inactive"}` | todas pasan a `inactive` |
| `PUT /api/products/{id}` con `{"status": "active"}` | todas vuelven a `active` |
| `PUT /api/products/{id}` sin cambiar `status` | sin efecto |

La cascada solo se dispara cuando el valor **cambia**. Si mandas el formulario completo con el
mismo `status` (lo normal en un PUT), las variantes no se tocan.

**Qué puedes quitar del frontend:** si estabas recorriendo las variantes para desactivarlas una
por una después de dar de baja el producto, ya no hace falta. Y si no lo hacías, aquí está el
bug que reportaron.

**Qué esperar en la respuesta:** tras la baja, el producto devuelve `totalStock: 0` y todas sus
variantes con `status: "inactive"`.

La cascada es **solo hacia abajo**: dar de baja una variante suelta
(`DELETE /api/products/{id}/variants/{variantId}`) no afecta al producto.

---

## 2. Las variantes ahora llegan en un orden definido

**Antes no había ningún `ORDER BY`** sobre las variantes. El orden lo decidía el motor según el
índice que eligiera, y medido en dos motores distintos salió distinto. No era un contrato: habría
cambiado solo al crecer el catálogo.

**Ahora llegan por color alfabético y, dentro de cada color, por talla.** Aplica a
`GET /api/products`, `POST /api/products/query`, `GET /api/products/{id}` y
`GET /api/products/{id}/variants`.

```
CAM-001-34-AZM
CAM-001-36-AZM
CAM-001-38-AZM
CAM-001-34-BLA
CAM-001-36-BLA
CAM-001-38-BLA
```

El orden de talla sale de `sizes.sort_order`, **no** del nombre ni del `id`. Esto importa para
las tallas por letra: CH, M, G, XG salen en ese orden y no alfabético, siempre que su `sortOrder`
esté bien capturado en el catálogo de tallas. Los colores van por nombre porque `colors` no tiene
columna de orden.

**Qué puedes quitar del frontend:** si estabas reordenando las variantes al recibirlas, ya puedes
confiar en el orden que llega.

**Ojo con un detalle:** en el listado de productos, `o` ordena **los productos, no las
variantes**. El orden de las variantes dentro de cada producto no es configurable desde ahí. Si
necesitas otro orden, usa `POST /api/products/{id}/variants/query` con `o` (ver sección 5).

---

## 3. Un producto inactivo ya no puede tener variantes activas

Se cerraron dos caminos por los que todavía se podía dejar el estado inconsistente:

| Caso | Antes | Ahora |
|---|---|---|
| `POST /api/products` con `status: "inactive"` | las variantes nacían `active` | nacen `inactive` |
| `POST /api/products/{id}/variants` sobre un producto inactivo | nacían `active` | nacen `inactive` |
| `PUT /api/products/{id}/variants/{variantId}` con `status: "active"` y producto inactivo | se aceptaba | **422** |

**El 422 es nuevo** y conviene manejarlo:

```json
{
  "message": "No se puede activar una variante de un producto inactivo: reactiva primero el producto.",
  "errors": {
    "status": ["No se puede activar una variante de un producto inactivo: reactiva primero el producto."]
  }
}
```

La salida es reactivar el producto, que de paso reactiva todas sus variantes.

**Lo que sí sigue permitido**, para que no lo bloquees de más:

- Editar `sku` o `codeBar` de una variante de un producto inactivo.
- Desactivar una variante de un producto inactivo (es idempotente).
- Tener una variante `inactive` dentro de un producto `active` — es un estado legítimo, por
  ejemplo una talla descontinuada. Si creas un producto activo y mandas una variante con
  `status: "inactive"`, se respeta.

---

## 4. Dashboard: los totales ya no cuentan productos dados de baja

`totalVariants`, `totalStock`, `inventoryValue` e `inventorySaleValue` en `data.summary` ahora
excluyen las variantes de productos inactivos, igual que ya hacían `lowestStock`, `highestStock`
y `criticalStockBySize`.

**Impacto:** si tienes productos inactivos en la base, esos cuatro números van a **bajar**
respecto a lo que mostraban antes. No es un error: antes sumaban inventario de productos dados
de baja.

---

## 5. Ordenamiento de los grids: 422 en lugar de 500

`o[column]` y `o[direction]` se pasaban crudos al `ORDER BY`. Dos formas de provocar un **500**:

| Entrada | Antes | Ahora |
|---|---|---|
| `o[column]` que no es columna de la tabla | 500 | 422 en `o.column` |
| `o[direction]` distinto de `asc` / `desc` | 500 | 422 en `o.direction` |

```json
{
  "message": "No se puede ordenar por 'sku': no es una columna de 'products'.",
  "errors": { "o.column": ["No se puede ordenar por 'sku': no es una columna de 'products'."] }
}
```

Aplica a los nueve recursos con grid: `products`, `products/{id}/variants`, `categories`,
`colors`, `sizes`, `size-groups`, `currencies`, `users` e `inventory/movements`.

### Cuidado con las llaves de la respuesta que no son columnas

Este es el punto a revisar. La respuesta trae llaves que **no** son ordenables porque no existen
como columna. En productos: `totalStock`, `sizeGroup`, `categories` y `variants`. Si tu grid
permite ordenar por el encabezado de alguna de esas, hoy daba 500 y ahora dará 422.

### Lo bueno: ya puedes ordenar la matriz de variantes por color y talla

En `POST /api/products/{id}/variants/query` se aceptan dos alias que antes daban 500:

| `o[column]` | Ordena por |
|---|---|
| `color` | nombre del color y, dentro de cada color, por talla |
| `size` | `sizes.sort_order` y, dentro de cada talla, por color |

`o[direction]` afecta al criterio principal; el secundario queda siempre ascendente. Sin `o`, el
orden por defecto es el de la sección 2 (color, luego talla).

---

## 6. Resumen de acciones sugeridas

| # | Acción | Prioridad |
|---|---|---|
| 1 | Quitar el bucle que desactivaba variantes una por una tras dar de baja el producto, si existe | Alta |
| 2 | Manejar el **422** al activar una variante de un producto inactivo | Alta |
| 3 | Revisar qué columnas permite ordenar el grid y quitar las que no son columnas reales (`totalStock`, `sizeGroup`, `categories`) | Alta |
| 4 | Quitar el reordenamiento local de variantes, si existe | Media |
| 5 | Avisar que los cuatro totales del dashboard pueden bajar si hay productos inactivos | Media |
| 6 | Aprovechar `o[column]=color` y `o[column]=size` para ordenar la matriz de variantes | Baja (mejora) |
| 7 | Revisar que el `sortOrder` del catálogo de tallas esté bien capturado, sobre todo en tallas por letra | Baja |

---

## 7. Documentación actualizada

- **UI navegable:** `/docs` (o `public/docs/index.html`)
- **Postman:** `public/docs/collection.json` — reimportar, incluye el 422 nuevo
- **OpenAPI:** `public/docs/openapi.yaml`
- **Interno:** `docs/inventario.md` (cascada, orden de variantes, coherencia de status,
  ordenamiento de grids) y `docs/dashboard.md` (los totales)

---

## 8. Commits para profundizar

Cada commit es autocontenido: incluye el cambio, sus tests y la documentación. El mensaje
explica el *por qué* y las alternativas descartadas.

| Commit | Qué contiene |
|---|---|
| `e05258c` | Cascada de status producto → variantes, por las dos vías. Incluye una migración que realinea los datos que ya quedaron desincronizados. |
| `0d77abf` | Orden de las variantes por color y talla. Explica por qué se ordena con subconsultas y no con `JOIN`. |
| `cd8524b` | Herencia de status al crear y el 422 al activar. Corrige `totals()` del dashboard. |
| `c73d9bc` | Anotaciones de Scribe y regeneración de las docs públicas. |
| `8291289` | Validación de `o[column]` / `o[direction]` en los nueve grids, y los alias `color` / `size`. |

Para verlos:

```bash
git log --oneline e987400..8291289      # la lista
git show e05258c                        # un commit completo, con diff
git show --stat 8291289                 # solo los archivos tocados
```

Los tests que fijan todo esto están en `tests/Feature/`:
`ProductStatusCascadeTest`, `ProductVariantOrderingTest`, `ProductStatusInvariantTest` y
`GridOrderValidationTest`. Se corren con `php artisan test`.

### Una nota sobre los tests

Dos de estos bugs **no se detectaban** en la suite porque SQLite (que usan los tests) los
enmascara: cita los identificadores con comillas dobles y degrada uno que no resuelve a literal
de texto, así que `order by "sku"` pasaba como un no-op en lugar de fallar. En MariaDB es
`1054 Unknown column`. Los dos se verificaron a mano contra la base real, y los tests nuevos
fijan el contrato por la respuesta HTTP en lugar de por el error del motor.
