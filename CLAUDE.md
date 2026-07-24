# tienda-backend

Backend API del sistema de ventas. Laravel 13 + PHP 8.3 + MariaDB.

## Stack
- **Framework**: Laravel 13
- **PHP**: 8.3
- **DB**: MariaDB (driver `mysql` en Laravel)
- **Auth**: JWT via `php-open-source-saver/jwt-auth`
- **API Docs**: Scribe (`php artisan scribe:generate`)

## Comandos frecuentes

```bash
php artisan serve              # Levantar servidor local (puerto 8000)
php artisan migrate --seed     # Correr migraciones + seeders
php artisan migrate:fresh --seed  # Resetear BD y resembrar
php artisan scribe:generate    # Regenerar docs públicas y Postman collection
php artisan storage:link       # Requerido una vez: expone las imágenes de productos en /storage
```

## Variables de entorno clave

| Variable | Descripción |
|---|---|
| `DB_DATABASE` | Nombre de la base de datos MariaDB |
| `DB_USERNAME` / `DB_PASSWORD` | Credenciales MariaDB |
| `JWT_SECRET` | Clave de firma JWT (generada con `php artisan jwt:secret`) |
| `JWT_ALGO` | Algoritmo JWT (default: `HS256`) |

## Documentación por módulo

- [Autenticación](docs/auth.md)
- [Usuarios](docs/usuarios.md)
- [Inventario](docs/inventario.md) — Categorías, productos, imágenes
- [Dashboard](docs/dashboard.md) — Métricas de ventas e inventario
- [Configuración](docs/config.md) — Tipos IVA, impuestos base, tipos de moneda
- [Base de datos](docs/database.md)

## Docs públicas (Scribe)

Generadas en `public/docs/`. Incluyen:
- `public/docs/index.html` — UI navegable (servida en `/docs` con `php artisan serve`)
- `public/docs/collection.json` — Postman collection (importar directo)
- `public/docs/openapi.yaml` — Especificación OpenAPI 3.x

Para regenerar después de cambiar rutas o controladores:
```bash
php artisan scribe:generate
```

## Arquitectura

```
app/
├── Http/
│   ├── Controllers/   # Delgados: reciben FormRequest, llaman al Service, retornan JSON
│   ├── Requests/      # Validación HTTP + método toDTO()
│   └── Resources/     # Transforma modelos al formato JSON de respuesta
├── DTOs/              # Objetos tipados (readonly class) para Controller → Service
├── Services/          # Lógica de negocio
├── Models/            # Eloquent: relaciones, fillable, casts
└── Support/
    └── ApiResponse.php  # Helper: formato estándar { ok, code, status, message, data }
```

Patrón: **FormRequest → DTO → Service → Model → Resource**

Todas las respuestas usan `ApiResponse::ok()`, `::created()` o `::error()`.

## Convención de nombres

- **API (entrada y salida): camelCase.** Todos los endpoints reciben y devuelven las llaves
  en camelCase (`legalName`, `stockControl`, `idCategory`). No se aceptan llaves en
  snake_case en la entrada: se ignoran.
- **Base de datos: snake_case.** Las columnas son snake_case (`legal_name`, `stock_control`,
  `id_category`).
- La conversión camelCase → snake_case ocurre en el `FormRequest` (`toDTO()`), no en el
  Service ni en el Model. Ejemplo: `NormalizesCompanyInfoInput::validatedSnake()` reindexa las
  llaves validadas con `Str::snake()` antes de armar el DTO.
- La salida se arma en el `Resource`, que mapea columnas snake_case → llaves camelCase.
