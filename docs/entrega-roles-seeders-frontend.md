# Entrega: roles, autorización, sesiones y seeders

Este documento resume los cambios realizados en el backend y las adaptaciones
que debe considerar el frontend. La autorización real se aplica en el servidor;
ocultar opciones en la interfaz mejora la experiencia, pero no sustituye las
restricciones de la API.

## 1. Modelo de roles

Se mantienen los tipos de usuario en `user_types`, pero ahora cada rol tiene un
`code` único y estable. El nombre es únicamente una etiqueta visible y puede
cambiar sin afectar permisos.

| Código estable | Nombre inicial |
|---|---|
| `administrator` | Administrador |
| `seller` | Vendedor |
| `warehouse` | Almacén |

La migración agrega `user_types.code`, convierte roles antiguos reconocidos y
crea una restricción única. La autorización ya no depende del texto
`administrador` ni de un ID concreto.

No se introdujeron tablas de módulos o permisos. La versión actual usa RBAC
sencillo mediante middleware de rutas. Un rol nuevo no recibe acceso funcional
hasta que se agregue explícitamente a la ruta correspondiente.

## 2. Matriz de acceso vigente

| Área | Administrador | Vendedor | Almacén |
|---|---:|---:|---:|
| Login y logout | Sí | Sí | Sí |
| Catálogos | Sí | Sí | Sí |
| Categorías, tallas y colores | Sí | Sí | Sí |
| Productos, variantes e imágenes | Sí | Sí | Sí |
| Inventario y movimientos | Sí | Sí | Sí |
| Dashboard | Sí | No | No |
| Usuarios | Sí | No | No |
| Empresa y monedas | Sí | No | No |

La restricción cubre todas las operaciones del área. Por ejemplo, Vendedor y
Almacén no pueden listar, crear, actualizar ni desactivar usuarios o monedas.

## 3. Autenticación y sesiones

Todas las rutas API, excepto `POST /api/login`, validan JWT y sesión registrada.
Además, en cada petición se comprueba que:

- El usuario continúe activo.
- Su tipo de usuario continúe activo.
- La sesión no esté revocada ni expirada.
- El código de rol esté autorizado para la ruta.

Se revocan todas las sesiones del usuario cuando:

- Se desactiva el usuario.
- Se le cambia de rol.
- Se cambia su contraseña.
- El middleware detecta que el usuario o su rol están inactivos.

`DELETE /api/logout` está disponible para cualquier usuario autenticado y
activo; ya no es exclusivo del administrador.

### Códigos HTTP relevantes

| Código | Significado para el frontend |
|---:|---|
| `401` | Token ausente, inválido, expirado o revocado; usuario o rol inactivo |
| `403` | Sesión válida, pero el rol no tiene acceso al endpoint |
| `422` | Datos inválidos; incluye asignar a un usuario un tipo de usuario inactivo |

## 4. Cambios en los contratos JSON

Los cambios son aditivos: `userType` se conserva para compatibilidad.

### Respuesta de login

`data.user` ahora incluye:

```json
{
  "id": 1,
  "firstName": "Administrador",
  "lastName": "Sistema",
  "userName": "admin",
  "email": "admin@tienda.local",
  "userType": 1,
  "roleCode": "administrator",
  "roleName": "Administrador"
}
```

- `userType`: sigue siendo el ID del tipo de usuario.
- `roleCode`: debe utilizarse para decisiones de navegación y acceso visual.
- `roleName`: sirve únicamente para mostrar la etiqueta al usuario.

### Catálogo de tipos de usuario

Cada elemento de `data.catalogs.userTypes` y `GET /api/catalogs` incluye:

```json
{
  "id": 1,
  "name": "Administrador",
  "code": "administrator",
  "status": "active"
}
```

Las respuestas del CRUD de usuarios también agregan `roleCode` y conservan
`idUserType` y `userType`.

### Catálogo de IVA

Se conservaron los cuatro IDs existentes y se reemplazaron las etiquetas
provisionales:

| ID | Nombre | Campo relacionado |
|---:|---|---|
| 1 | General | Tasa general |
| 2 | Por producto | `rateIva`, porcentaje propio del producto |
| 3 | Cuota fija | `quotaIva`, importe fijo por unidad |
| 4 | No aplica | Sin IVA |

No existe una tabla `iva_types`; el catálogo permanece fijo en código.

## 5. Cambios requeridos en frontend

### Obligatorios

1. Guardar `data.user.roleCode` al iniciar sesión.
2. Usar el código, no el ID ni `roleName`, para mostrar módulos:
   - `administrator`: Dashboard, Usuarios y Configuración.
   - `seller` y `warehouse`: ocultar esas tres áreas.
3. Mantener Productos, Inventario y Catálogos visibles para los tres roles.
4. Ante cualquier `401`, limpiar token y estado de sesión y redirigir al login.
5. Ante `403`, mostrar “No tienes permisos” sin tratarlo como sesión expirada.
6. Después de cambiar la contraseña del usuario actual, limpiar el token y
   redirigir al login aunque el `PUT /api/users/{id}` haya respondido `200`;
   esa sesión ya fue revocada.

Ejemplo de tipo recomendado en TypeScript:

```ts
type RoleCode = "administrator" | "seller" | "warehouse";
```

### Recomendados

- Construir las opciones de rol usando `catalogs.userTypes` y enviar su `id` en
  `idUserType`; no fijar que Administrador siempre sea ID 1.
- Mostrar solamente tipos con `status === "active"` al crear o editar usuarios.
- Conservar un guard de rutas del frontend para impedir navegación directa a
  pantallas administrativas.
- Mantener manejo global de `403`, porque el backend sigue siendo la fuente de
  verdad si un usuario intenta llamar manualmente un endpoint restringido.

### Lo que no cambia

- El formato `Authorization: Bearer <JWT>`.
- Las rutas y payloads existentes de productos, inventario y configuración.
- El campo de entrada `idUserType` en creación y actualización de usuarios.
- Los IDs y campos de IVA (`typeIva`, `rateIva`, `quotaIva`).
- No existe una bandera `mustChangePassword` ni un flujo obligatorio de primer
  acceso.

## 6. Bootstrap y datos iniciales

Las migraciones crean esquema y realizan transformaciones de compatibilidad,
pero no dependen de que existan monedas, empresa o catálogos. La asociación de
empresa con MXN se realiza en el seeder productivo, después de crear las monedas.

### Producción

Con `APP_ENV=production`, `DatabaseSeeder` ejecuta `ProductionSeeder`, que crea
idempotentemente:

- Los tres roles.
- MXN con tasa 1 y USD con tasa inicial 17.25.
- La información actual de Guayaberas Lopez Silva, asociada con MXN.
- Los grupos Adultos y Niños y sus 23 tallas.
- El usuario `admin` con contraseña `admin`, solo si `users` está vacío.

Si ya existe cualquier usuario, ejecutar el seeder no crea `admin/admin`. Una
segunda ejecución tampoco restablece contraseñas, tasas, estados o configuración
existente.

Comando productivo:

```bash
php artisan migrate --seed --force
```

La contraseña inicial `admin` debe cambiarse desde Usuarios por una de al menos
8 caracteres. No hay bloqueo técnico de primer acceso.

### Desarrollo

En cualquier entorno distinto de producción, `DatabaseSeeder` ejecuta
`DevelopmentSeeder`. Este reutiliza el baseline productivo y agrega las
categorías, colores, productos, variantes y movimientos demo actuales.

```bash
php artisan migrate:fresh --seed
```

`DevelopmentSeeder` aborta si alguien intenta ejecutarlo con
`APP_ENV=production`.

## 7. Validación realizada

- 154 pruebas automatizadas aprobadas.
- 565 aserciones.
- Pruebas específicas de matriz de roles, estados, revocación de sesiones,
  bootstrap productivo, bootstrap de desarrollo e idempotencia.
- OpenAPI, colección Postman y documentación navegable regenerados.

Los cambios se encuentran en el commit `a3ae8db` de la rama `develop`.
