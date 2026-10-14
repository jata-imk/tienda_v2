# Base de datos

Motor: **MariaDB**. Configurar en `.env` con `DB_CONNECTION=mysql`.

## Diagrama de relaciones

```
user_types (1) ──< users (N) ──< user_sessions (N)
company_info       (independiente — datos de la empresa)
```

## Tablas

### `user_types`
Catálogo de roles de usuario.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `name` | varchar | Etiqueta visible del rol |
| `code` | varchar unique | Código estable usado para autorización |
| `status` | enum | `active` / `inactive` |
| `created_at` | timestamp | Fecha de creación |
| `updated_at` | timestamp nullable | Fecha de actualización |

### `companies`
Datos de la empresa. Se retorna en cada login.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `status` | enum | `Active` / `Inactive` |
| `company_name` | varchar | Nombre comercial |
| `rfc` | varchar nullable | RFC fiscal |
| `razon_social` | varchar nullable | Razón social |
| `regimen_fiscal` | varchar nullable | Régimen fiscal |
| `img` | text nullable | Logo en base64 |
| `street` | varchar nullable | Calle |
| `num_ext` | varchar nullable | Número exterior |
| `cross_one` | varchar nullable | Entre calle 1 |
| `cross_two` | varchar nullable | Entre calle 2 |
| `cp` | varchar nullable | Código postal |
| `colony` | varchar nullable | Colonia |
| `city` | varchar nullable | Ciudad |
| `stock_control` | boolean | Control de inventario (default: true) |
| `integers_q` | tinyint | Dígitos enteros en cantidades (default: 9) |
| `decimals_q` | tinyint | Decimales en cantidades (default: 3) |
| `date_creation` | timestamp | Fecha de creación |

### `tokens`
JWT emitidos. Permite invalidar tokens sin esperar expiración.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `status` | enum | `vigente` / `caducado` |
| `token` | text | String JWT completo |
| `date_start` | timestamp | Fecha de emisión |
| `date_expiration` | timestamp | Fecha de expiración (24h después de emisión) |

### `usuarios`
Credenciales y datos personales del usuario.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `user_type_id` | bigint FK | → `tipos_usuario.id` |
| `name` | varchar | Nombre |
| `first_name` | varchar | Primer apellido |
| `last_name` | varchar | Segundo apellido |
| `username` | varchar unique | Nombre de usuario para login |
| `email` | varchar unique | Correo electrónico |
| `password` | varchar | Hash bcrypt |
| `status` | enum | `activo` / `inactivo` |
| `date_creation` | timestamp | Fecha de creación |

### `sesiones`
Relación activa entre un usuario y su token JWT.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `session` | json nullable | Datos adicionales de sesión |
| `user_id` | bigint FK | → `usuarios.id` |
| `token_id` | bigint FK | → `tokens.id` |
| `status` | enum | `vigente` / `finalizado` |
| `date_start` | timestamp | Inicio de sesión |
| `date_end` | timestamp nullable | Fin de sesión (null si sigue activa) |

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

### Cambio en `inventory_movements`

`movement_type` agrega `purchase` (entrada por recepción de pedido) e índice compuesto
(`reference_type`, `reference_id`).

## Seeders

Las migraciones crean y transforman estructura, pero no insertan el baseline
operativo. `DatabaseSeeder` selecciona uno de estos orquestadores según `APP_ENV`:

| Seeder | Entorno | Datos |
|---|---|---|
| `ProductionSeeder` | `production` | Roles, MXN/USD, empresa, grupos, tallas y primer `admin` si no hay usuarios |
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
