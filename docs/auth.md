# Autenticación

## Endpoint

```
POST /api/login
Content-Type: application/json
```

## Request

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `usurrio` | string | sí | Nombre de usuario (campo `username` en BD) |
| `pass` | string | sí | Contraseña en texto plano |

```json
{
  "usurrio": "suriel.dzul",
  "pass": "suriel2024"
}
```

> El campo se llama `usurrio` (con typo) porque así lo define el cliente en el JSON de referencia.

## Respuesta exitosa (200)

```json
{
  "result": "ok",
  "message": "Inicio de sesión exitoso",
  "data": {
    "token": "<JWT válido 24h>",
    "empresa": {
      "nombre": "Guayaberas Lopez Silva",
      "logo": null,
      "modoOscuro": false,
      "configImp": [],
      "fechaUpdate": "2024-01-01 00:00:00",
      "settings": { "grids": [] }
    },
    "user": {
      "nombre": "Suriel",
      "primerApellido": "Dzul",
      "segundoApellido": "Dzul",
      "usuario": "suriel.dzul",
      "email": "dzulsuriel@gmail.com",
      "tipoUsuario": "1",
      "permisos": []
    }
  }
}
```

## Respuesta de error (401)

```json
{
  "result": "error",
  "message": "Contraseña incorrecta",
  "data": null
}
```

## Flujo de lógica (`AuthService`)

```
1. Buscar usuario por `username` en tabla `usuarios`
2. Verificar status = 'activo'
3. Hash::check(pass_input, hash_bcrypt_guardado)
4. Buscar sesión vigente (status='vigente') del usuario
   ├── SIN sesión  → crear JWT → guardar en `tokens` → crear `sesiones`
   └── CON sesión  → ¿token vigente y no expirado?
         ├── SÍ → reutilizar mismo JWT
         └── NO → marcar caducado → crear JWT nuevo → nueva sesión
5. Retornar token + empresa + user
```

## Contraseña

- Almacenada como hash **bcrypt** (Laravel `Hash::make()`)
- Nunca se guarda ni transmite en texto plano
- Verificación: `Hash::check($input, $hash)` — unidireccional, no hay "descifrado"
- Rondas de bcrypt: `BCRYPT_ROUNDS=12` en `.env`

## JWT

- Paquete: `php-open-source-saver/jwt-auth`
- Algoritmo: `HS256` (simétrico, firmado con `JWT_SECRET`)
- Expiración: **24 horas** desde la creación
- Payload incluye: `sub` (user id), `username`, `user_type`, `iat`, `exp`
- El JWT se guarda en la tabla `tokens` para tracking de sesiones en BD

## Tablas involucradas

| Tabla | Rol |
|---|---|
| `usuarios` | Credenciales y datos del usuario |
| `tipos_usuario` | Tipo/rol del usuario (ej. administrador) |
| `tokens` | JWT emitidos con su estado y fecha de expiración |
| `sesiones` | Relación usuario ↔ token activo |
| `companies` | Datos de la empresa retornados en el login |

Ver esquema completo en [database.md](database.md).

## Usuario de prueba (seeder)

| Campo | Valor |
|---|---|
| username | `suriel.dzul` |
| password | `suriel2024` |
| email | dzulsuriel@gmail.com |
| tipo | administrador |
