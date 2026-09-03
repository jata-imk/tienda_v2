# Guía de Integración Frontend: Endpoints POST .../query y Kardex

> **Para el desarrollador frontend y su agente de IA:**
> Este documento explica los cambios implementados en el backend del POS (`tienda-backend`), el contrato de consulta unificado para componentes de cuadrícula (**DevExtreme DataGrid / CustomStore** u otros), los endpoints nuevos disponibles y cómo consumirlos. Es agnóstico de framework: contiene contratos HTTP reales, tipos TypeScript listos para copiar y ejemplos de integración.

---

## 1. Resumen Ejecutivo de Cambios

1. **Unificación del patrón POST /{recurso}/query:**
   - Todos los recursos tabulares del sistema ahora cuentan con su versión `POST /api/{recurso}/query` además del `GET /api/{recurso}` estándar.
   - Ambos reciben y procesan los mismos parámetros de paginación, filtros y ordenación, pero `POST .../query` envía los parámetros en un cuerpo JSON para evitar limitaciones de longitud de URL (HTTP 414) y problemas de codificación con árboles de filtros complejos.
2. **Corrección de precedencia de operadores OR:**
   - Se resolvió un bug en el backend donde filtros con operadores `||` (OR) podían anular filtros `AND` previos (por ejemplo, `status = active`). Ahora los bloques OR se agrupan automáticamente entre paréntesis en SQL.
3. **Nuevos endpoints disponibles:**
   - **Usuarios**: `POST /api/users/query` y `GET /api/users` con soporte de grid.
   - **Historial de Inventario (Kardex)**: `GET /api/inventory/movements` y `POST /api/inventory/movements/query`.
   - **Variantes de Producto**: `POST /api/products/{id}/variants/query`.
   - **Monedas**: `POST /api/currencies/query`.
4. **Campo virtual `search` universal:**
   - Ahora todos los catálogos y recursos admiten el filtro rápido `{ "f": "search", "ao": "contains", "v": "texto" }` para búsquedas globales desde barras de búsqueda en la UI sin tener que armar múltiples condiciones `||` a mano.

---

## 2. Mapa Completo de Endpoints de Consulta

Todos los endpoints protegidos requieren:
```http
Authorization: Bearer <token>
Content-Type: application/json
```

| Recurso | GET estándar | POST query (Recomendado para DataGrid) | Descripción |
|---|---|---|---|
| **Productos** | `GET /api/products` | `POST /api/products/query` | Listado y búsqueda avanzada de productos |
| **Variantes** | `GET /api/products/{id}/variants` | `POST /api/products/{id}/variants/query` | Matriz y variantes (talla x color) de un producto |
| **Categorías** | `GET /api/categories` | `POST /api/categories/query` | Catálogo de categorías |
| **Grupos de tallas** | `GET /api/size-groups` | `POST /api/size-groups/query` | Catálogo de grupos de tallas |
| **Tallas** | `GET /api/sizes` | `POST /api/sizes/query` | Catálogo de tallas |
| **Colores** | `GET /api/colors` | `POST /api/colors/query` | Catálogo de colores |
| **Usuarios** | `GET /api/users` | `POST /api/users/query` | Listado y administración de usuarios |
| **Kardex (Movimientos)**| `GET /api/inventory/movements` | `POST /api/inventory/movements/query` | Historial de auditoría y movimientos de stock |
| **Monedas** | `GET /api/currencies` | `POST /api/currencies/query` | Catálogo de monedas y tipos de cambio |
| **Dashboard** | `GET /api/dashboard` | `POST /api/dashboard/query` | Métricas y KPIs de ventas e inventario |

---

## 3. Contrato Estándar de Petición (`POST .../query`)

El payload JSON acepta 5 bloques principales (todos opcionales):

```jsonc
{
  // 1. Paginación: Formato estándar o DevExtreme
  "p": {
    "page": 0,        // o "r": 0 (row offset, 0-indexed)
    "per_page": 15    // o "s": 15 (take / page size)
  },

  // 2. Selección de campos (en camelCase): optimiza el SELECT en BD
  "f": ["id", "name", "status"],

  // 3. Ordenamiento: Formato estándar o DevExtreme
  "o": {
    "column": "name",   // o "field": "name"
    "direction": "asc"  // o "type": "asc" / "desc"
  },

  // 4. Filtros: Lista de condiciones DevExtreme o diccionario simple
  "w": [
    { "f": "status", "ao": "==", "v": "active", "lo": "&&" },
    { "f": "search", "ao": "contains", "v": "lino", "lo": "&&" }
  ],

  // 5. Total de registros coincidentes para el paginador
  "totalCount": true
}
```

### Operadores de Filtro Soportados (`ao`)

| `ao` | Significado | Ejemplo de valor |
|---|---|---|
| `==` | Igualdad exacta (default) | `"active"`, `1` |
| `!=` / `<>` | Diferente de | `"inactive"` |
| `>`, `>=`, `<`, `<=` | Comparación numérica o de fechas | `10`, `"2026-01-01"` |
| `contains` | Búsqueda parcial (`LIKE '%valor%'`) | `"camisa"` |
| `notcontains` | No contiene (`NOT LIKE '%valor%'`) | `"azul"` |
| `startswith` | Comienza con (`LIKE 'valor%'`) | `"CAM-"` |
| `endswith` | Termina con (`LIKE '%valor'`) | `"-001"` |
| `between` | Rango de valores (inclusivo) | `[100, 500]` o `["2026-01-01", "2026-01-31"]` |
| `in` / `anyof` | Pertenece a una lista | `[1, 2, 5]` |
| `notin` / `noneof` | No pertenece a la lista | `[3, 4]` |

### Conector Lógico (`lo`)
- `"&&"`: Conecta con la condición anterior usando `AND`. Inicia un nuevo bloque de agrupación.
- `"||"`: Conecta con la condición anterior usando `OR`. El backend agrupa automáticamente las condiciones `||` entre paréntesis con la condición `&&` anterior para no anular filtros críticos como `status = active`.

---

## 4. Contrato Estándar de Respuesta (`ApiResponse::query`)

Todas las consultas devuelven el envelope estándar compatible con el `CustomStore` de DevExtreme:

```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "Items retrieved.",
  "data": {
    "items": [
      // Arreglo de objetos transformados
    ],
    "totalCount": 150,
    "summary": [ 150 ]
  }
}
```

---

## 5. Especificación de los Nuevos Endpoints

### 5.1 Usuarios — `POST /api/users/query`

Permite construir la pantalla de gestión de usuarios con búsqueda, filtro por rol y estado.

**Campos disponibles para filtrar / ordenar:**
- `id`: ID de usuario
- `idUserType` / `user_type`: ID o nombre del tipo de usuario (FK)
- `firstName`, `lastName`, `userName`, `email`: Campos de texto
- `status`: `"active"` / `"inactive"`
- `search`: Búsqueda sobre `firstName`, `lastName`, `userName`, `email` y rol.

**Ejemplo de Request:**
```http
POST /api/users/query
Content-Type: application/json

{
  "p": { "r": 0, "s": 10 },
  "o": { "column": "userName", "direction": "asc" },
  "w": [
    { "f": "status", "ao": "==", "v": "active", "lo": "&&" },
    { "f": "search", "ao": "contains", "v": "dzul", "lo": "&&" }
  ],
  "totalCount": true
}
```

**Ejemplo de Response (200):**
```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "Users retrieved.",
  "data": {
    "items": [
      {
        "id": 1,
        "idUserType": 1,
        "userType": "administrador",
        "firstName": "Suriel",
        "lastName": "Dzul",
        "userName": "suriel.dzul",
        "email": "dzulsuriel@gmail.com",
        "status": "active",
        "createdAt": "2024-01-01 00:00:00",
        "updatedAt": "2026-08-10 12:00:00"
      }
    ],
    "totalCount": 1,
    "summary": [1]
  }
}
```

---

### 5.2 Kardex / Movimientos de Inventario — `POST /api/inventory/movements/query`

Consulta el historial de auditoría de entradas, salidas, ventas, ajustes y cancelaciones de existencias.

**Campos disponibles para filtrar / ordenar:**
- `idProduct` / `product`: ID del producto (resuelto automáticamente a través de la variante).
- `idProductVariant`: ID de la variante específica.
- `sku`: SKU de la variante.
- `movementType`: `"entry"` | `"sale"` | `"adjustment"` | `"return"` | `"cancel"`.
- `idUser`: Usuario que generó el movimiento.
- `referenceType`: Origen (`"manual_adjustment"`, `"sales_note"`, `"initial_load"`).
- `createdAt`: Fecha de creación (`"2026-08-01"` o rango con `between`).
- `search`: Búsqueda sobre notas, origen, SKU, nombre del producto y clave interna (`key`).

**Ejemplo de Request:**
```http
POST /api/inventory/movements/query
Content-Type: application/json

{
  "p": { "r": 0, "s": 20 },
  "o": { "column": "id", "direction": "desc" },
  "w": [
    { "f": "idProduct", "ao": "==", "v": 25, "lo": "&&" },
    { "f": "movementType", "ao": "in", "v": ["entry", "adjustment"], "lo": "&&" }
  ],
  "totalCount": true
}
```

**Ejemplo de Response (200):**
```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "Inventory movements retrieved.",
  "data": {
    "items": [
      {
        "id": 501,
        "idProductVariant": 101,
        "movementType": "adjustment",
        "quantity": 2.0,
        "previousStock": 8.0,
        "newStock": 6.0,
        "referenceType": "manual_adjustment",
        "referenceId": null,
        "notes": "Ajuste por conteo físico",
        "idUser": 1,
        "createdAt": "2026-08-12 21:45:00",
        "userName": "suriel.dzul",
        "idProduct": 25,
        "productName": "Camisa lino caballero",
        "productKey": "CAM-001",
        "sku": "CAM-001-34-BLA",
        "size": "34",
        "color": "Blanco"
      }
    ],
    "totalCount": 1,
    "summary": [1]
  }
}
```

---

### 5.3 Variantes de Producto — `POST /api/products/{id}/variants/query`

Permite paginar y filtrar la matriz de variantes de un producto puntual (útil cuando un producto maneja decenas de combinaciones talla x color).

**Campos disponibles para filtrar / ordenar:**
- `idSize`: ID de talla.
- `size` / `sizeName`: Nombre de talla (`"34"`, `"M"`).
- `idColor`: ID de color.
- `color` / `colorName`: Nombre de color (`"Blanco"`).
- `sku`: SKU puntual o búsqueda parcial.
- `codeBar`: Código de barras.
- `stock`: Existencia (`>`, `==`, etc.).
- `status`: `"active"` / `"inactive"`.
- `search`: Búsqueda sobre SKU, código de barras, nombre de talla y nombre de color.

**Ejemplo de Request:**
```http
POST /api/products/25/variants/query
Content-Type: application/json

{
  "p": { "page": 1, "per_page": 15 },
  "w": [
    { "f": "stock", "ao": ">", "v": 0, "lo": "&&" },
    { "f": "color", "ao": "==", "v": "Blanco", "lo": "&&" }
  ],
  "totalCount": true
}
```

---

### 5.4 Monedas — `POST /api/currencies/query`

**Campos disponibles:**
- `name`: Nombre de la moneda.
- `code`: Código ISO (`"MXN"`, `"USD"`).
- `symbol`: Símbolo (`"$"`).
- `status`: `"active"` / `"inactive"`.
- `search`: Búsqueda en nombre, código y símbolo.

---

## 6. Tipos TypeScript para el Frontend

Puedes copiar estos tipos directamente en tu proyecto (por ejemplo en `src/types/api.ts`):

```typescript
// ─── Tipos Base de Consulta ──────────────────────────────────────────────────

export type Status = 'active' | 'inactive';

export type GridOperator =
  | '=='
  | '!='
  | '<>'
  | '>'
  | '>='
  | '<'
  | '<='
  | 'contains'
  | 'notcontains'
  | 'startswith'
  | 'endswith'
  | 'between'
  | 'in'
  | 'notin'
  | 'anyof'
  | 'noneof';

export interface GridCondition {
  f: string;               // Columna o campo virtual (ej. 'search', 'status')
  ao?: GridOperator;       // Operador (default: '==')
  v: unknown;              // Valor o lista de valores
  lo?: '&&' | '||';        // Conector lógico (default: '&&')
}

export interface GridQueryParams {
  p?: {
    page?: number;         // Offset o número de página
    per_page?: number;     // Tamaño de página
    r?: number;            // Row offset DevExtreme
    s?: number;            // Page size DevExtreme
  };
  f?: string[];            // Columnas a devolver (camelCase)
  o?: {
    column?: string;       // Columna a ordenar
    direction?: 'asc' | 'desc';
    field?: string;        // Alias DevExtreme
    type?: 'asc' | 'desc'; // Alias DevExtreme
  };
  w?: Record<string, unknown> | GridCondition[];
  totalCount?: boolean;
}

export interface GridResponse<T> {
  ok: boolean;
  code: number;
  status: string;
  message: string;
  data: {
    items: T[];
    totalCount: number;
    summary: number[];
  };
}

// ─── Usuarios ─────────────────────────────────────────────────────────────────

export interface UserRow {
  id: number;
  idUserType: number;
  userType: string | null;
  firstName: string;
  lastName: string;
  userName: string;
  email: string;
  status: Status;
  createdAt: string | null;
  updatedAt: string | null;
}

// ─── Kardex / Movimientos ─────────────────────────────────────────────────────

export type MovementType = 'entry' | 'sale' | 'adjustment' | 'return' | 'cancel';

export interface InventoryMovementRow {
  id: number;
  idProductVariant: number;
  movementType: MovementType;
  quantity: number;
  previousStock: number;
  newStock: number;
  referenceType: string | null;
  referenceId: number | null;
  notes: string | null;
  idUser: number;
  createdAt: string;
  // Campos enriquecidos expuestos por el backend:
  userName?: string | null;
  idProduct?: number;
  productName?: string;
  productKey?: string;
  sku?: string;
  size?: string;
  color?: string;
}

// ─── Variantes de Producto ────────────────────────────────────────────────────

export interface ProductVariantRow {
  id: number;
  idProduct: number;
  idSize: number;
  size: string;
  idColor: number;
  color: string;
  hexColor: string | null;
  sku: string;
  codeBar: string | null;
  stock: number;
  status: Status;
}

// ─── Monedas ──────────────────────────────────────────────────────────────────

export interface CurrencyRow {
  id: number;
  name: string;
  code: string;
  symbol: string;
  exchangeRate: number;
  status: Status;
  createdAt: string | null;
  updatedAt: string | null;
}
```

---

## 7. Adaptador Genérico DevExtreme `CustomStore`

Si usas Angular, React o Vue con DevExtreme, este adaptador mapea las opciones remotas de DevExtreme al formato `POST /{recurso}/query`:

```typescript
import CustomStore from 'devextreme/data/custom_store';
import { HttpClient } from '@angular/common/http'; // o axios / fetch

export function createDevExtremeQueryStore<T>(
  apiUrl: string,
  httpClient: HttpClient
): CustomStore {
  return new CustomStore({
    key: 'id',
    load: async (loadOptions) => {
      const payload: GridQueryParams = {
        p: {
          r: loadOptions.skip ?? 0,
          s: loadOptions.take ?? 15,
        },
        totalCount: loadOptions.requireTotalCount ?? true,
      };

      // Ordenación
      if (loadOptions.sort && loadOptions.sort.length > 0) {
        payload.o = {
          column: loadOptions.sort[0].selector,
          direction: loadOptions.sort[0].desc ? 'desc' : 'asc',
        };
      }

      // Filtros
      if (loadOptions.filter) {
        // Si usas el parser nativo de DevExtreme, envíalo directamente
        // o mapea las condiciones a GridCondition[]
        payload.w = loadOptions.filter as any;
      }

      const response = await httpClient
        .post<GridResponse<T>>(apiUrl, payload)
        .toPromise();

      return {
        data: response.data.items,
        totalCount: response.data.totalCount,
        summary: response.data.summary,
      };
    },
  });
}
```

---

## 8. Colección Postman y OpenAPI

Los archivos generados automáticamente para importar en Postman o en herramientas de tipado OpenAPI se encuentran en el backend:
- **OpenAPI 3.0**: `public/docs/openapi.yaml`
- **Postman Collection**: `public/docs/collection.json`
- **Documentación web navegable**: Visitar `/docs` con el backend en ejecución (`php artisan serve`).
