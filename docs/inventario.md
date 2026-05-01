# Inventario

CRUD de categorías y productos. Todos los endpoints requieren:
- Header `Authorization: Bearer <JWT>`
- Usuario con tipo `administrador`

---

## /api/categorias — CRUD completo

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/categorias` | Listar |
| `GET` | `/api/categorias/{id}` | Ver una |
| `POST` | `/api/categorias` | Crear |
| `PUT` | `/api/categorias/{id}` | Actualizar |
| `DELETE` | `/api/categorias/{id}` | Desactivar (soft-delete) |

**Campos:**
| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `name` | string | sí | Nombre de la categoría |
| `description` | string | no | Descripción |
| `status` | string | no | activo / inactivo (default: activo) |

---

## /api/inventario — CRUD completo

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/inventario` | Listar |
| `GET` | `/api/inventario/{id}` | Ver uno |
| `POST` | `/api/inventario` | Crear |
| `PUT` | `/api/inventario/{id}` | Actualizar |
| `DELETE` | `/api/inventario/{id}` | Dar de baja (status → 'baja') |

**Campos de creación:**
| Campo | Tipo | Req. | Descripción |
|---|---|---|---|
| `category_id` | int | sí | FK → categorias |
| `clave` | string | sí | Código interno único (expuesto como `key` en respuesta) |
| `name` | string | sí | Nombre del producto |
| `description` | string | no | Descripción |
| `codebar` | string | no | Código de barras (string para preservar ceros) |
| `price` | decimal | sí | Precio base **sin IVA** |
| `cost` | decimal | sí | Costo |
| `stock_control` | boolean | sí | Activar control de stock |
| `stock` | decimal | sí | Stock inicial |
| `discount` | decimal | no | % descuento sobre price (default: 0) |
| `type_iva_id` | int | sí | FK → tipos_iva (1=general, 2=tasa, 3=cuota, 4=no aplica) |
| `rate_iva` | decimal | cond. | Tasa IVA específica. Requerido si `type_iva_id=2` |
| `quota_iva` | decimal | cond. | Cuota IVA fija por unidad. Requerido si `type_iva_id=3` |
| `isr` | decimal | no | % ISR del producto (default: 0) |
| `imp_esp` | decimal | no | % impuesto especial (default: 0) |
| `status` | string | no | activo / baja (default: activo) |

**Respuesta de producto:**
```json
{
  "ok": true, "code": 200, "status": "OK",
  "message": "Producto obtenido.",
  "data": {
    "id": 1,
    "idCategory": 1,
    "category": "Camisas lino",
    "status": "activo",
    "key": "000001",
    "name": "Guayabera blanca",
    "description": "Guayabera caballero 100% lino",
    "codebar": "8888888888881",
    "price": 800.00,
    "cost": 600.00,
    "stockControl": true,
    "stock": 20.000,
    "discount": 0.00,
    "typeIVA": 1,
    "tipoIva": "general",
    "rateIVA": null,
    "quotaIVA": null,
    "ISR": 0.00,
    "impESP": 0.00,
    "dateCreation": "2024-01-01 00:00:00"
  }
}
```

## Nota sobre impuestos

- `price` es siempre **precio base sin IVA**
- El IVA aplicable se calcula según `typeIVA` usando `impuestos_config` como base (ver [config.md](config.md))
- `discount`, `ISR` e `impESP` son porcentajes aplicados sobre `price`
