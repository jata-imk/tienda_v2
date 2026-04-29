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
```

## Variables de entorno clave

| Variable | Descripción |
|---|---|
| `DB_DATABASE` | Nombre de la base de datos MariaDB |
| `DB_USERNAME` / `DB_PASSWORD` | Credenciales MariaDB |
| `JWT_SECRET` | Clave de firma JWT (generada con `php artisan jwt:secret`) |
| `JWT_ALGO` | Algoritmo JWT (default: `HS256`) |

## Documentación por módulo

- [Autenticación](docs/auth.md) — Login, JWT, sesiones, usuarios
- [Base de datos](docs/database.md) — Esquema de tablas y relaciones

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
├── Http/Controllers/   # Delgados: validan request, llaman al Service, retornan JSON
├── Services/           # Lógica de negocio (AuthService, etc.)
└── Models/             # Eloquent: relaciones, fillable, casts
```

El patrón es **MVC + Service Layer**: el controlador no tiene lógica de negocio,
toda la lógica vive en el Service correspondiente.
