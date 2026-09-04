# Tienda Backend

API REST para sistema de ventas. Módulo inicial: autenticación.

**Stack:** Laravel 13 · PHP 8.3 · MariaDB · JWT

---

## Requisitos

- PHP 8.3+
- Composer 2.x
- MariaDB / MySQL

## Instalación

```bash
git clone https://github.com/jata-imk/tienda_v2.git
cd tienda_v2

composer install

cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edita `.env` con tus credenciales de base de datos. En desarrollo:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

En producción, con `APP_ENV=production`, usa:

```bash
php artisan migrate --seed --force
```

`DatabaseSeeder` selecciona el dataset por entorno. Producción recibe únicamente
el baseline operativo; desarrollo añade datos demo. La cuenta inicial de una BD
sin usuarios es `admin` / `admin` y su contraseña debe cambiarse inmediatamente.

La API queda disponible en `http://localhost:8000`.

## Git hooks

El proyecto incluye un hook `pre-commit` que regenera la documentación automáticamente cuando cambian rutas o controladores. Actívalo con:

```bash
git config core.hooksPath .githooks
```

## Documentación

| Recurso | Ruta |
|---|---|
| UI navegable | `http://localhost:8000/docs` |
| Postman collection | `public/docs/collection.json` |
| OpenAPI YAML | `public/docs/openapi.yaml` |
| Auth (interno) | `docs/auth.md` |
| Roles (interno) | `docs/roles.md` |
| Entrega backend/frontend | `docs/entrega-roles-seeders-frontend.md` |
| Base de datos (interno) | `docs/database.md` |

Para regenerar manualmente:

```bash
php artisan scribe:generate
```

## Módulos

- **Auth** — Login/logout con JWT, gestión de sesiones y tokens
- **Roles** — Administrador, Vendedor y Almacén con control de acceso por middleware
- **Inventario** — Catálogos, productos, variantes y movimientos
- **Configuración** — Empresa y monedas

## Endpoints

```
POST /api/login
DELETE /api/logout
```

Ver documentación completa en `docs/auth.md` o en la UI de docs.
