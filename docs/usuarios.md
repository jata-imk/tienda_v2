# Usuarios

CRUD de usuarios. Todos los endpoints requieren:
- Header `Authorization: Bearer <JWT>` (obtenido del login)
- Usuario autenticado con tipo `administrador`

---

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/usuarios` | Listar todos |
| `GET` | `/api/usuarios/{id}` | Ver uno |
| `POST` | `/api/usuarios` | Crear |
| `PUT` | `/api/usuarios/{id}` | Actualizar |
| `DELETE` | `/api/usuarios/{id}` | Desactivar (soft-delete) |

---

## GET /api/usuarios

```json
// Response 200
{
  "result": "ok",
  "message": "Usuarios obtenidos.",
  "data": [
    {
      "id": 1,
      "nombre": "Suriel",
      "primerApellido": "Dzul",
      "segundoApellido": "Dzul",
      "usuario": "suriel.dzul",
      "email": "dzulsuriel@gmail.com",
      "tipoUsuario": "administrador",
      "status": "activo",
      "dateCreation": "2024-01-01 00:00:00"
    }
  ]
}
```

---

## POST /api/usuarios

```json
// Request
{
  "name": "Juan",
  "first_name": "Pérez",
  "last_name": "López",
  "username": "juan.perez",
  "email": "juan@empresa.com",
  "password": "secreto123",
  "user_type_id": 1,
  "status": "activo"        // opcional, default: activo
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

**`auth:api`** — Verifica el JWT con `JWTAuth`. Si el token es inválido o expirado retorna 401.

**`es.administrador`** — Verifica que `usuario->tipoUsuario->type_user === 'administrador'`. Retorna 403 si no cumple.
