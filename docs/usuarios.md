# Usuarios

CRUD de usuarios. Todos los endpoints requieren:
- Header `Authorization: Bearer <JWT>` (obtenido del login)
- Usuario autenticado con código de rol `administrator`

---

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/users` | Listar usuarios (soporta filtros de grid) |
| `POST` | `/api/users/query` | Consultar usuarios (filtros avanzados en body) |
| `GET` | `/api/users/{id}` | Ver uno |
| `POST` | `/api/users` | Crear usuario |
| `PUT` | `/api/users/{id}` | Actualizar usuario |
| `DELETE` | `/api/users/{id}` | Desactivar (status → inactive) |

---

## GET /api/users y POST /api/users/query

Ambos endpoints soportan paginación (`p`), selección de columnas (`f`), ordenamiento (`o`), filtros (`w`) y conteo (`totalCount`).

En `POST /api/users/query` el body acepta:
- `p`: `{ "page": 0, "per_page": 15 }` o `{ "r": 0, "s": 15 }`
- `f`: `["id", "userName", "email", "status"]`
- `o`: `{ "column": "userName", "direction": "asc" }`
- `w`: Lista de filtros DevExtreme o asociativo `{ "status": "active" }`
- `w` soporta el campo virtual `search`: busca sobre nombre, apellidos, usuario, email y rol.
- `totalCount`: `true` / `false`

```json
// Response 200
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
        "userType": "Administrador",
        "roleCode": "administrator",
        "firstName": "Suriel",
        "lastName": "Dzul",
        "userName": "admin",
        "email": "admin@tienda.local",
        "status": "active",
        "createdAt": "2024-01-01 00:00:00",
        "updatedAt": "2024-01-01 00:00:00"
      }
    ],
    "totalCount": 1,
    "summary": [1]
  }
}
```

---

## POST /api/users

```json
// Request
{
  "firstName": "Juan",
  "lastName": "Pérez López",
  "userName": "juan.perez",
  "email": "juan@empresa.com",
  "password": "secreto123",
  "idUserType": 1,
  "status": "active"        // opcional, default: active
}

// Response 201
{
  "result": "ok",
  "message": "Usuario creado.",
  "data": { ...usuario }
}
```

---

## PUT /api/usuarios/{id}

Todos los campos son opcionales. Solo enviar los que se quieran modificar.
Para cambiar contraseña incluir `password`.

```json
// Request (ejemplo parcial)
{
  "name": "Juan Actualizado",
  "status": "inactivo"
}

// Response 200
{
  "result": "ok",
  "message": "Usuario actualizado.",
  "data": { ...usuario }
}
```

---

## DELETE /api/usuarios/{id}

No elimina el registro — cambia `status` a `inactivo` para conservar historial.

```json
// Response 200
{
  "result": "ok",
  "message": "Usuario desactivado.",
  "data": null
}
```

---

## Errores comunes

| Código | Causa |
|---|---|
| 401 | Sin token o token inválido/expirado |
| 403 | Usuario autenticado no es administrador |
| 404 | Usuario no encontrado |
| 422 | Validación fallida (username/email duplicado, campos requeridos) |

---

## Middleware de protección

**`jwt.authenticate`** — Verifica el JWT, la sesión y que el usuario y su rol estén activos. Si no se cumple retorna 401.

**`role:administrator`** — Autoriza mediante `user_types.code`, sin depender del nombre visible. Retorna 403 si el rol no cumple.
