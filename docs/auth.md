# Autenticación y autorización

## Login

```http
POST /api/login
Content-Type: application/json
```

```json
{
  "userName": "admin",
  "password": "admin"
}
```

El usuario y su tipo deben tener `status: active`. La respuesta mantiene `userType`
como ID por compatibilidad y expone el código estable utilizado por el frontend:

```json
{
  "ok": true,
  "code": 200,
  "status": "OK",
  "message": "Login successful",
  "data": {
    "token": "<JWT>",
    "user": {
      "id": 1,
      "firstName": "Administrador",
      "lastName": "Sistema",
      "userName": "admin",
      "email": "admin@tienda.local",
      "userType": 1,
      "roleCode": "administrator",
      "roleName": "Administrador"
    },
    "companyInfo": {},
    "catalogs": {}
  }
}
```

Un usuario inexistente, contraseña incorrecta, usuario inactivo o tipo de usuario
inactivo recibe `401`.

En una instalación productiva sin usuarios, el seeder crea temporalmente
`admin` / `admin`. No existe bloqueo de primer acceso: debe cambiarse desde el
módulo Usuarios por una contraseña de al menos 8 caracteres. El cambio revoca
las sesiones existentes y obliga a iniciar sesión nuevamente.

## JWT y sesiones

- El guard `api` utiliza `php-open-source-saver/jwt-auth`.
- El JWT expira según `JWT_TTL` y debe existir en `user_sessions`.
- Cada petición comprueba que la sesión no esté expirada ni revocada.
- También se comprueba en cada petición el estado actual del usuario y de su rol.
- Desactivar un usuario, desactivar su rol o cambiarlo de rol revoca sus sesiones.
- Los claims del JWT no son la fuente de autorización; se consulta la BD.

## Logout

```http
DELETE /api/logout
Authorization: Bearer <JWT>
```

Está disponible para cualquier usuario autenticado cuyo usuario y rol estén activos.

## Roles

Consulta la matriz vigente en [roles.md](roles.md).
