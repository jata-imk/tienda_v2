# Base de datos

Motor: **MariaDB**. Configurar en `.env` con `DB_CONNECTION=mysql`.

## Diagrama de relaciones

```
tipos_usuario (1) ──< usuarios (N)
tokens        (1) ──< sesiones (N) >── (1) usuarios
companies           (independiente — datos de la empresa)
```

## Tablas

### `tipos_usuario`
Catálogo de roles de usuario.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | Auto-incremental |
| `type_user` | varchar | Nombre del tipo (ej. "administrador") |
| `status` | enum | `activo` / `inactivo` |
| `date_creation` | timestamp | Fecha de creación (default: now) |

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

## Seeders

| Seeder | Datos |
|---|---|
| `UserTypeSeeder` | tipo id=1 "administrador" activo |
| `CompanySeeder` | empresa "Guayaberas Lopez Silva" |
| `UserSeeder` | usuario `suriel.dzul` / pass `suriel2024` |

Correr con:
```bash
php artisan migrate --seed
# o para resetear todo:
php artisan migrate:fresh --seed
```
