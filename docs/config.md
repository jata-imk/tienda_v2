# Configuración

Catálogos y parámetros de configuración del sistema.

Todos los endpoints requieren:
- Header `Authorization: Bearer <JWT>`
- Usuario con tipo `administrador`

---

## GET /api/tipos-iva

Catálogo de tipos de IVA. Solo lectura (4 registros fijos).

```json
// Response 200
{
  "ok": true, "code": 200, "status": "OK",
  "message": "Tipos de IVA obtenidos.",
  "data": [
    { "id": 1, "name": "general",        "description": "General (base: 16%)" },
    { "id": 2, "name": "tasa_producto",  "description": "Tasa por producto" },
    { "id": 3, "name": "cuota_producto", "description": "Cuota por producto" },
    { "id": 4, "name": "no_aplica",      "description": "No aplica" }
  ]
}
```

**Lógica de cálculo por tipo:**
| id | Cálculo |
|---|---|
| 1 | IVA = price × (`impuestos_config.iva` / 100) |
| 2 | IVA = price × (`inventario.rate_iva` / 100) |
| 3 | IVA = `inventario.quota_iva` × cantidad (cuota fija) |
| 4 | IVA = 0 |

---

## GET /api/impuestos-config

Ver los porcentajes base de impuestos.

```json
// Response 200
{
  "ok": true, "code": 200, "status": "OK",
  "message": "Configuración de impuestos obtenida.",
  "data": { "iva": 16.0, "isr": 10.0, "impEsp": 0.0, "dateCreation": "...", "dateUpdate": null }
}
```

## PUT /api/impuestos-config

Actualiza uno o más porcentajes. Solo los campos enviados se modifican.

```json
// Request (ejemplo: reducción temporal de IVA)
{ "iva": 8 }

// Response 200
{
  "ok": true, "code": 200, "status": "OK",
  "message": "Configuración de impuestos actualizada.",
  "data": { "iva": 8.0, "isr": 10.0, "impEsp": 0.0, "dateCreation": "...", "dateUpdate": "..." }
}
```

---

## /api/tipos-moneda — CRUD completo

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/tipos-moneda` | Listar |
| `GET` | `/api/tipos-moneda/{id}` | Ver uno |
| `POST` | `/api/tipos-moneda` | Crear |
| `PUT` | `/api/tipos-moneda/{id}` | Actualizar |
| `DELETE` | `/api/tipos-moneda/{id}` | Desactivar (soft-delete) |

**Campos de creación:**
| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `name` | string | sí | Nombre (ej. Pesos Mexicanos) |
| `code` | string | sí | Código ISO 3 letras (ej. MXN) — se guarda en mayúsculas |
| `symbol` | string | sí | Símbolo (ej. $) |
| `status` | string | no | activo / inactivo (default: activo) |

Registro default (seeder): `{ name: 'Pesos Mexicanos', code: 'MXN', symbol: '$' }`
