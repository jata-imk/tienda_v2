# Configuración

Catálogos y parámetros de configuración del sistema.

Todos los endpoints requieren:
- Header `Authorization: Bearer <JWT>`
- Usuario con tipo `administrador`

---

## GET /api/tipos-iva

> **No implementado.** No hay ruta registrada en `routes/api.php` ni tabla `tipos_iva`.
> Esta sección describe el diseño previsto, no un endpoint disponible.

Catálogo de tipos de IVA. Solo lectura (4 registros fijos).

```json
// Response 200
{
  "ok": true, "code": 200, "status": "OK",
  "message": "Tipos de IVA obtenidos.",
  "data": [
    { "id": 1, "name": "General" },
    { "id": 2, "name": "Por producto" },
    { "id": 3, "name": "Cuota fija" },
    { "id": 4, "name": "No aplica" }
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

> **No implementado.** No hay ruta registrada en `routes/api.php` ni tabla `impuestos_config`.
> Esta sección describe el diseño previsto, no un endpoint disponible.

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

## /api/currencies — CRUD completo

Registrado con `Route::post('currencies/query', ...)` y `Route::apiResource('currencies', ...)`.

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/currencies` | Listar (soporta filtros de grid `p`, `f`, `o`, `w`, `totalCount`) |
| `POST` | `/api/currencies/query` | Consultar monedas (filtros avanzados en body) |
| `GET` | `/api/currencies/{id}` | Ver uno |
| `POST` | `/api/currencies` | Crear |
| `PUT` | `/api/currencies/{id}` | Actualizar |
| `DELETE` | `/api/currencies/{id}` | Desactivar (soft-delete) — 409 si es la moneda base |

**Campos de creación:**
| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `name` | string | sí | Nombre (ej. Pesos Mexicanos) |
| `code` | string | sí | Código ISO 3 letras (ej. MXN) — se guarda en mayúsculas |
| `symbol` | string | sí | Símbolo (ej. $) |
| `exchangeRate` | number | no | Tipo de cambio manual (default: 1). Debe ser > 0 |
| `status` | string | no | activo / inactivo (default: activo) |

### Tipo de cambio

Se captura **a mano**: no hay consumo de API externa ni job programado.

`exchangeRate` = cuántas unidades de la **moneda base** equivalen a 1 unidad de esta moneda.
Con base MXN, `USD.exchangeRate = 17.25` significa que 1 USD son 17.25 MXN.

La moneda base (la referenciada por `company-info.idCurrency`) debe tener `exchangeRate = 1`.
No se fuerza por constraint: cambiar de base implicaría reescribir todas las tasas.

El valor se guarda como `decimal(18,6)` y **no lleva histórico**. Si más adelante se necesita
"el tipo de cambio del día de la venta", habrá que copiar la tasa a la fila de la venta
(snapshot), no leerla del catálogo.

Registros default (seeder): `{ name: 'Pesos Mexicanos', code: 'MXN', symbol: '$', exchangeRate: 1 }`
y `{ name: 'Dólar Estadounidense', code: 'USD', symbol: '$', exchangeRate: 17.25 }`

> Al desplegar la migración `2026_08_27_000001`, todas las monedas existentes reciben inicialmente
> `exchangeRate = 1`. `ProductionSeeder` crea MXN y USD cuando no existen, pero nunca sobrescribe
> tasas capturadas posteriormente.

**Rango válido:** entre `0.000001` y `999999999999` (el de `decimal(18,6)`). Fuera de ahí la API
responde 422 en vez de dejar que la BD redondee a 0 o desborde. Los decimales por encima de 6 se
redondean.

---

## /api/company-info — Datos de la empresa

Tabla de **un solo registro**.

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/company-info` | Ver el registro (404 si no existe) |
| `POST` | `/api/company-info` | Alta (409 si ya existe uno) |
| `PUT` | `/api/company-info` | Reemplazo completo (`name` requerido) |
| `PATCH` | `/api/company-info` | Actualización parcial |

**Campos:** `name` (req.), `rfc`, `legalName`, `taxRegime`, `idCurrency`, `logo`, `street`,
`externalNumber`, `crossStreetOne`, `crossStreetTwo`, `postalCode`, `neighborhood`, `city`,
`stockControl`, `quantityIntegers`, `quantityDecimals`, `gridSettings` (objeto), `status`.

Entrada y salida en **camelCase** (igual que el resto de la API); las columnas de la tabla
son snake_case. Las llaves en snake_case se ignoran.

### Moneda base

`idCurrency` apunta a un registro de `/api/currencies` y define la moneda en la que opera el
negocio. Es opcional (`null` si no se ha configurado) y solo acepta monedas con `status: active`.

La moneda base está protegida: `DELETE /api/currencies/{id}` sobre ella responde 409. La FK de la
tabla es `RESTRICT`, pero nunca dispara porque el borrado es lógico (`status: inactive`), así que
el bloqueo vive en `CurrencyService::destroy()`.

La respuesta incluye además el objeto `currency` ya resuelto, para no tener que cruzarlo con el
catálogo solo para pintar el símbolo:

```json
// PATCH /api/company-info  →  { "idCurrency": 2 }
{
  "ok": true, "code": 200, "status": "OK",
  "message": "Company info updated.",
  "data": {
    "name": "Guayaberas Lopez Silva",
    "idCurrency": 2,
    "currency": {
      "id": 2, "name": "Dólar Estadounidense", "code": "USD",
      "symbol": "$", "exchangeRate": 17.25, "status": "active"
    }
  }
}
```

El mismo objeto `currency` viaja en `data.companyInfo` de la respuesta de login.

El tipo de cambio **no se edita desde aquí**: se cambia en `PUT /api/currencies/{id}`.

### Logo

`logo` es una cadena **base64**, con o sin prefijo data-URI
(`data:image/png;base64,...`). Máximo ~2.8 MB de texto ≈ 2 MB de imagen. La columna es
`LONGTEXT`: antes era `TEXT` (64 KB) y truncaba los logos.

A diferencia de la imagen de producto, aquí no se guarda archivo en disco — es una sola imagen.

```json
{ "name": "Mi Tienda SA", "logo": "data:image/png;base64,iVBORw0KGgo...", "stockControl": true }
```
