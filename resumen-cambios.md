# Resumen de cambios — tienda-backend

Fecha: 2026-05-07  
Rama: `develop`  
Commit: `c502a0f`

---

## 1. Base de datos

### Tablas renombradas / reestructuradas

| Tabla anterior | Tabla nueva | Cambios principales |
|---|---|---|
| `tipos_usuario` | `user_types` | Columna `type_user` → `name` |
| `companies` | `company_info` | `company_name`→`name`, `razon_social`→`legal_name`, `regimen_fiscal`→`tax_regime`, `colony`→`neighborhood`, `cp`→`postal_code`, `num_ext`→`external_number`, `cross_one/two`→`cross_street_one/two`, `img`→`logo`, `integers_q`→`quantity_integers`, `decimals_q`→`quantity_decimals`, columna nueva `grid_settings` (JSON) |
| `usuarios` | `users` | `username`→`user_name`, `user_type_id`→`id_user_type`, eliminada columna `name` (separada en `first_name` + `last_name`) |
| `sesiones` + `tokens` | `user_sessions` | **Fusión de las dos tablas.** El status de la sesión se deriva de fechas (`expires_at`, `revoked_at`), ya no hay columna `status` |
| `tipos_moneda` | `currencies` | Sin cambios de columnas, solo nombre de tabla |
| `categorias` | `categories` | Sin cambios de columnas, solo nombre de tabla |
| `inventario` | `products` | `category_id`→`id_category`, `clave`→`key`, `codebar`→`code_bar`, columna nueva `size`, eliminada FK a `tipos_iva` → reemplazada por `type_iva` (entero 1-4) |

### Tablas eliminadas

| Tabla | Motivo |
|---|---|
| `tipos_iva` | Catálogo pequeño y estático — se maneja como constante en código (1=general 16%, 2=tasa, 3=cuota, 4=no aplica) |
| `impuestos_config` | Misma razón — los valores base van como constantes |
| `tokens` | Fusionada en `user_sessions` |

### Reglas globales aplicadas a todas las tablas

- Todas las tablas tienen `created_at` y `updated_at` gestionados automáticamente por Laravel (`timestamps()`).
- Eliminada la columna manual `date_creation` de todas las tablas.
- No se usa `deleted_at` (soft deletes). El sistema ya maneja activación/desactivación con columna `status` (`active` / `inactive`).
- Todas las PKs permanecen como `id`.
- Las FKs siguen el formato `id_<relación>` (ej. `id_category`, `id_user`, `id_user_type`).

---

## 2. API — Endpoints

Base URL: `/api`  
Autenticación: `Authorization: Bearer <token>` en todos los endpoints protegidos.

### Autenticación

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| `POST` | `/login` | No | Iniciar sesión |
| `DELETE` | `/logout` | Sí | Cerrar sesión (revoca el token activo) |

### Usuarios

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/users` | Listar usuarios |
| `POST` | `/users` | Crear usuario |
| `GET` | `/users/{id}` | Ver usuario |
| `PUT` | `/users/{id}` | Actualizar usuario |
| `DELETE` | `/users/{id}` | Desactivar usuario (`status = inactive`) |

### Categorías

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/categories` | Listar (soporta filtros, ver §3) |
| `POST` | `/categories` | Crear categoría |
| `GET` | `/categories/{id}` | Ver categoría |
| `PUT` | `/categories/{id}` | Actualizar categoría |
| `DELETE` | `/categories/{id}` | Desactivar categoría |

### Productos

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/products` | Listar (soporta filtros, ver §3) |
| `POST` | `/products` | Crear producto |
| `GET` | `/products/{id}` | Ver producto |
| `PUT` | `/products/{id}` | Actualizar producto |
| `DELETE` | `/products/{id}` | Desactivar producto |

### Monedas

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/currencies` | Listar monedas |
| `POST` | `/currencies` | Crear moneda |
| `GET` | `/currencies/{id}` | Ver moneda |
| `PUT` | `/currencies/{id}` | Actualizar moneda |
| `DELETE` | `/currencies/{id}` | Desactivar moneda |

---

## 3. Filtros de consulta (categories y products)

Los endpoints de listado aceptan los siguientes query params:

```
GET /products?p[page]=1&p[per_page]=15&f[]=id&f[]=name&o[column]=name&o[direction]=asc&w[status]=active&w[id_category]=1&totalCount=true
```

| Param | Descripción | Ejemplo |
|---|---|---|
| `p[page]` | Número de página | `p[page]=1` |
| `p[per_page]` | Registros por página | `p[per_page]=15` |
| `f[]` | Columnas a retornar (select) | `f[]=id&f[]=name` |
| `o[column]` | Columna para ordenar | `o[column]=name` |
| `o[direction]` | Dirección: `asc` o `desc` | `o[direction]=asc` |
| `w[columna]` | Filtro where exacto | `w[status]=active` |
| `totalCount` | Si `true`, retorna total de registros | `totalCount=true` |

Respuesta con `totalCount=true`:

```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "...",
  "data": {
    "items": [ ... ],
    "total": 42
  }
}
```

---

## 4. Estructura de respuestas API

Todas las respuestas siguen el mismo formato:

```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "Descripción del resultado",
  "data": { }
}
```

---

## 5. Tipos TypeScript (sugerencia para el frontend)

```typescript
// ─── Enums y constantes ──────────────────────────────────────────────────────

export type Status = 'active' | 'inactive';

/** 1=general (16%), 2=tasa, 3=cuota, 4=no aplica */
export type TypeIVA = 1 | 2 | 3 | 4;

// ─── Respuesta base ───────────────────────────────────────────────────────────

export interface ApiResponse<T = unknown> {
  ok: boolean;
  code: number;
  status: string;
  message: string;
  data: T;
}

export interface PaginatedData<T> {
  items: T[];
  total: number;
}

// ─── Auth ─────────────────────────────────────────────────────────────────────

export interface LoginPayload {
  userName: string;
  password: string;
}

export interface AuthUser {
  firstName: string;
  lastName: string;
  userName: string;
  email: string;
  userType: number; // id del tipo de usuario
}

export interface AuthCompanyInfo {
  name: string;
  logo: string | null;
  gridSettings: Record<string, unknown>;
  status: Status;
  updatedAt: string | null;
}

export interface LoginData {
  token: string;
  companyInfo: AuthCompanyInfo;
  user: AuthUser;
}

export type LoginResponse = ApiResponse<LoginData>;

// ─── Usuarios ─────────────────────────────────────────────────────────────────

export interface User {
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

export interface CreateUserPayload {
  idUserType: number;
  firstName: string;
  lastName: string;
  userName: string;
  email: string;
  password: string;
  status?: Status;
}

export interface UpdateUserPayload extends Partial<Omit<CreateUserPayload, 'password'>> {
  password?: string;
}

export type UserResponse = ApiResponse<User>;
export type UserListResponse = ApiResponse<User[]>;

// ─── Categorías ───────────────────────────────────────────────────────────────

export interface Category {
  id: number;
  name: string;
  description: string | null;
  status: Status;
  createdAt: string | null;
  updatedAt: string | null;
}

export interface CreateCategoryPayload {
  name: string;
  description?: string;
  status?: Status;
}

export type UpdateCategoryPayload = Partial<CreateCategoryPayload>;

export type CategoryResponse = ApiResponse<Category>;
export type CategoryListResponse = ApiResponse<Category[]>;
export type CategoryPaginatedResponse = ApiResponse<PaginatedData<Category>>;

// ─── Productos ────────────────────────────────────────────────────────────────

/** Categoría resuelta que devuelve la API en productos */
export interface ProductCategory {
  id: number;
  desc: string;
}

export interface Product {
  id: number;
  /** Un producto puede pertenecer a varias categorías */
  categories: ProductCategory[];
  key: string;
  name: string;
  description: string | null;
  codeBar: string | null;
  /** URL absoluta de la imagen (null si no tiene) */
  image: string | null;
  /** URL absoluta del thumbnail 200px */
  imageThumb: string | null;
  size: string | null;
  price: number;
  cost: number;
  stockControl: boolean;
  stock: number;
  discount: number;
  typeIVA: TypeIVA;
  rateIVA: number | null;
  quotaIVA: number | null;
  isr: number;
  impEsp: number;
  status: Status;
  createdAt: string | null;
  updatedAt: string | null;
}

export interface CreateProductPayload {
  /** Array de ids; la respuesta devuelve `categories: [{id, desc}]` */
  categories: number[];
  key: string;
  name: string;
  description?: string;
  codeBar?: string;
  size?: string;
  price: number;
  cost: number;
  stockControl: boolean;
  stock: number;
  discount?: number;
  typeIva: TypeIVA;
  rateIva?: number | null;
  quotaIva?: number | null;
  isr?: number;
  impEsp?: number;
  status?: Status;
}

export type UpdateProductPayload = Partial<CreateProductPayload>;

export type ProductResponse = ApiResponse<Product>;
export type ProductListResponse = ApiResponse<Product[]>;
export type ProductPaginatedResponse = ApiResponse<PaginatedData<Product>>;

// ─── Dashboard ────────────────────────────────────────────────────────────────

export interface DashboardQuery {
  limit?: number;              // 1-50, default 5
  dateFrom?: string;           // solo afecta topProducts
  dateTo?: string;
  lowStockThreshold?: number;  // default 5
}

export interface TopProduct {
  id: number;
  key: string;
  name: string;
  quantitySold: number;
}

export interface StockRankingRow {
  id: number;
  key: string;
  name: string;
  stock: number;
}

export interface DashboardSummary {
  totalProducts: number;
  activeProducts: number;
  totalVariants: number;
  totalStock: number;
  inventoryValue: number;
  lowStockCount: number;
}

export interface DashboardData {
  topProducts: TopProduct[];
  lowestStock: StockRankingRow[];
  highestStock: StockRankingRow[];
  summary: DashboardSummary;
}

export type DashboardResponse = ApiResponse<DashboardData>;

// ─── Monedas ──────────────────────────────────────────────────────────────────

export interface Currency {
  id: number;
  name: string;
  code: string;
  symbol: string;
  status: Status;
  createdAt: string | null;
  updatedAt: string | null;
}

export interface CreateCurrencyPayload {
  name: string;
  code: string;
  symbol: string;
  status?: Status;
}

export type UpdateCurrencyPayload = Partial<CreateCurrencyPayload>;

export type CurrencyResponse = ApiResponse<Currency>;
export type CurrencyListResponse = ApiResponse<Currency[]>;
```

---

## 6. Cambios del 21-07-2026

| Cambio | Impacto en el frontend |
|---|---|
| Categorías múltiples por producto | El campo se llama `categories` en entrada y salida: request `categories: [1,2]` (array de ids), response `categories: [{id, desc}]`. Desaparecen `idCategory` y `category`. Filtro: `w[categories]` (o `w[id_category]`). |
| Imagen de producto | `POST /api/products/{id}/image` (multipart, campo `image`) y `DELETE` para borrarla. La respuesta trae `image` e `imageThumb` como URLs absolutas. |
| `updatedAt` | Es `null` al dar de alta cualquier registro; solo se llena al modificar. |
| `company-info` | Nuevos `GET` y `POST /api/company-info` (409 si ya existe). `logo` en base64 (columna `LONGTEXT`). Entrada y salida en camelCase (igual que el resto). |
| Dashboard | Nuevo `GET /api/dashboard` (ver tipos arriba). |

---

## 7. Notas para el frontend

### Cambio en login

El campo de login es ahora camelCase:

```typescript
// Antes (snake_case)
{ user_name: 'suriel.dzul', password: '...' }

// Ahora (camelCase)
{ userName: 'suriel.dzul', password: '...' }
```

### Sesiones

- El backend reutiliza el token si ya hay una sesión activa (válida y no revocada). No se generan tokens duplicados.
- Al hacer logout (`DELETE /logout`), el token queda revocado en la BD. El frontend debe eliminar el token del storage local.

### Catálogo de tipos de IVA (`typeIVA`)

El catálogo ya no viene de la API — es fijo:

```typescript
const IVA_TYPES: Record<TypeIVA, string> = {
  1: 'General (16%)',
  2: 'Tasa',
  3: 'Cuota',
  4: 'No aplica',
};
```

Reglas de negocio:
- `typeIVA = 1` → `rateIVA = 16`, `quotaIVA = null`
- `typeIVA = 2` → `rateIVA` requerido, `quotaIVA = null`
- `typeIVA = 3` → `quotaIVA` requerido, `rateIVA = null`
- `typeIVA = 4` → ambos `null`
