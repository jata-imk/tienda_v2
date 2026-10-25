# Base de datos

Motor: **MariaDB**. Configurar en `.env` con `DB_CONNECTION=mysql`.

## Diagrama de relaciones

```
user_types (1) ──< users (N) ──< user_sessions (N)
currencies (1) ──< company_info      (una sola fila: datos de la empresa y moneda base)
```

Las tablas de inventario (`products`, `product_variants`, `inventory_movements`, catálogos de
tallas/colores/categorías) se documentan en [inventario.md](inventario.md); `currencies` en
[config.md](config.md); las de pedidos de compra, abajo.

## Tablas

### `user_types`
Catálogo de roles de usuario.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `name` | varchar | Etiqueta visible del rol |
| `code` | varchar(50) unique | Código estable usado para autorización (`administrator`, `seller`, `warehouse`) |
| `status` | enum | `active` / `inactive` |
| `created_at` | timestamp | Fecha de creación |
| `updated_at` | timestamp nullable | Fecha de actualización |

### `company_info`
Datos de la empresa. Se retorna en cada login y alimenta el PDF de pedidos.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `name` | varchar | Nombre comercial |
| `rfc` | varchar nullable | RFC fiscal |
| `legal_name` | varchar nullable | Razón social |
| `tax_regime` | varchar nullable | Régimen fiscal |
| `id_currency` | bigint FK nullable | → `currencies.id`. **Moneda base** del negocio |
| `logo` | longtext nullable | Logo en base64 (con o sin prefijo data-URI) |
| `street` / `external_number` | varchar nullable | Calle y número exterior |
| `cross_street_one` / `cross_street_two` | varchar nullable | Entre calles |
| `postal_code` / `neighborhood` / `city` | varchar nullable | CP, colonia y ciudad |
| `stock_control` | boolean | Control de inventario (default: true) |
| `quantity_integers` | tinyint | Dígitos enteros en cantidades (default: 9) |
| `quantity_decimals` | tinyint | Decimales en cantidades (default: 3) |
| `grid_settings` | json nullable | Preferencias de grids |
| `status` | enum | `active` / `inactive` |
| `created_at` / `updated_at` | timestamp | |

### `users`
Credenciales y datos personales del usuario.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `id_user_type` | bigint FK | → `user_types.id` |
| `first_name` | varchar | Nombre |
| `last_name` | varchar | Apellidos |
| `user_name` | varchar unique | Nombre de usuario para login |
| `email` | varchar unique | Correo electrónico |
| `password` | varchar | Hash bcrypt |
| `status` | enum | `active` / `inactive` |
| `created_at` / `updated_at` | timestamp | |

### `user_sessions`
JWT emitidos por sesión. Permite revocar tokens (logout, cambio de rol o de contraseña,
desactivación) sin esperar a que expiren.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `id_user` | bigint FK | → `users.id` |
| `token_hash` | text | Token de la sesión |
| `expires_at` | timestamp | Expiración |
| `revoked_at` | timestamp nullable | Cuándo se revocó (null = vigente) |
| `created_at` / `updated_at` | timestamp | |

## Pedidos de compra

```
currencies (1) ──< orders (N) ──< order_details (N) >── (1) product_variants
users (1) ──< orders            orders (1) ──< order_status_history (N)
order_details (1) ──< inventory_movements (N)   (reference_type = 'order_detail', reference_id)
```

Reglas de negocio y endpoints en [pedidos.md](pedidos.md).

### `orders`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `key` | varchar(10) | Serie del folio. Default `PED` |
| `folio` | int unsigned | Consecutivo por `key`. **Unique (`key`, `folio`)** |
| `status` | enum | `draft`, `confirmed`, `partially_received`, `received`, `cancelled` |
| `id_currency` | bigint FK | → `currencies.id` |
| `exchange_rate` | decimal(18,6) nullable | Null en draft (usa la de `currencies`); se congela al confirmar |
| `total_items` | decimal(12,3) | Σ `quantity_ordered` |
| `total_amount` | decimal(14,2) | Σ `subtotal` (sin impuestos) |
| `supplier_name` | varchar nullable | Proveedor, texto libre (no hay tabla `suppliers`) |
| `supplier_details` | text nullable | Dirección / RFC / contacto del proveedor |
| `shipping_address` | text nullable | Dirección de envío; null → la de `company_info` |
| `notes` | text nullable | Comentarios |
| `date_order` | date | Fecha del pedido |
| `date_expected` | date nullable | Fecha de entrega esperada |
| `date_received` | datetime nullable | Cuándo quedó `received` |
| `id_user` | bigint FK | → `users.id`, quien lo creó |
| `created_at` / `updated_at` | timestamp | `updated_at` null hasta la primera edición |

Índices: `status`, `date_order`.

### `order_details`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `id_order` | bigint FK | → `orders.id` (cascade on delete) |
| `id_product_variant` | bigint FK | → `product_variants.id` |
| `quantity_ordered` | decimal(10,3) | Cantidad pedida |
| `quantity_received` | decimal(10,3) | Acumulado recibido. Default 0 |
| `unit_cost` | decimal(10,2) | Costo unitario en la moneda del pedido |
| `subtotal` | decimal(12,2) | `quantity_ordered × unit_cost` |
| `notes` | text nullable | Comentario de la línea |
| `created_at` / `updated_at` | timestamp | |

Unique (`id_order`, `id_product_variant`); índice `id_product_variant`. En MariaDB, CHECK
`quantity_received BETWEEN 0 AND quantity_ordered` (en SQLite lo valida solo el Service).

### `order_status_history`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `id_order` | bigint FK | → `orders.id` (cascade on delete) |
| `previous_status` | varchar(20) nullable | Null en la creación |
| `new_status` | varchar(20) | |
| `id_user` | bigint FK | → `users.id` |
| `notes` | text nullable | Comentario de la transición |
| `created_at` | timestamp | |

### `document_sequences`

Último folio emitido por serie (`PED` para pedidos). `OrderService` bloquea la fila
(`SELECT … FOR UPDATE`) para sacar el siguiente folio: las altas concurrentes esperan su turno
en lugar de chocar. Calcular el folio con `MAX(folio) … FOR UPDATE` sobre `orders` producía
deadlocks en MariaDB con altas simultáneas (verificado: 3 de 6 fallaban). La fila de cada serie
se crea en la migración, no al vuelo.

| Columna | Tipo | Descripción |
|---|---|---|
| `key` | varchar(10) PK | Serie (`PED`) |
| `last_folio` | int unsigned | Último folio emitido |

### Cambio en `inventory_movements`

`movement_type` agrega `purchase` (entrada por recepción de pedido) e índice compuesto
(`reference_type`, `reference_id`).

## Claves de producto

Columnas para generar claves (reglas en [inventario.md](inventario.md#claves-automáticas)):

| Tabla | Columna | Tipo | Descripción |
|---|---|---|---|
| `categories` | `key` | varchar(10) unique | Prefijo de la clave de producto (`CAM`) |
| `size_groups` | `key` | varchar(10) unique nullable | Segmento de la clave (`NIN`) |
| `products` | `id_main_category` | FK nullable → categories | Categoría principal; siempre está también en `category_product` |

Backfill de las migraciones `2026_10_24_*` sobre datos existentes:

- `products.key` pasa a mayúsculas (`FIl-004` → `FIL-004`).
- Cada prefijo de clave ya capturado se asigna a la categoría de esos productos cuyo nombre
  empieza con él (`CAM` → Camisa, `VEST` → Vestidos); el resto de categorías recibe clave
  generada.
- El grupo de tallas "Niños" recibe `NIN`.
- `id_main_category` = categoría cuya clave es el prefijo de la clave del producto
  (`GUA-NIN-003` → Guayabera); si ninguna coincide, la primera de la pivote.

## Seeders

Las migraciones crean y transforman estructura, pero no insertan el baseline
operativo. `DatabaseSeeder` selecciona uno de estos orquestadores según `APP_ENV`:

| Seeder | Entorno | Datos |
|---|---|---|
| `ProductionSeeder` | `production` | Roles, MXN/USD, empresa (con logo), grupos, tallas y primer `admin` si no hay usuarios |
| `DevelopmentSeeder` | Otros | Todo producción más categorías, colores, productos e inventario demo |

Los seeders de catálogos son idempotentes: una segunda ejecución no duplica ni
restablece configuración existente. `DevelopmentSeeder` rechaza su ejecución en
producción aunque se invoque directamente.

Producción:

```bash
php artisan migrate --seed --force
```

Desarrollo:

```bash
php artisan migrate:fresh --seed
```

En una BD productiva sin usuarios se crea `admin` / `admin`. Debe cambiarse de
inmediato desde Usuarios; la nueva contraseña debe tener al menos 8 caracteres.
