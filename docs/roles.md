# Roles y acceso

Los nombres son etiquetas visibles. La autorización utiliza exclusivamente el
campo estable `user_types.code`.

| Código | Nombre inicial |
|---|---|
| `administrator` | Administrador |
| `seller` | Vendedor |
| `warehouse` | Almacén |

## Matriz actual

| Módulo | Administrador | Vendedor | Almacén |
|---|---:|---:|---:|
| Login y logout | Sí | Sí | Sí |
| Catálogos | Sí | Sí | Sí |
| Categorías, tallas y colores | Sí | Sí | Sí |
| Productos, variantes e imágenes | Sí | Sí | Sí |
| Inventario y movimientos | Sí | Sí | Sí |
| Dashboard | Sí | No | No |
| Usuarios | Sí | No | No |
| Configuración de empresa y monedas | Sí | No | No |

No existen todavía módulos o permisos almacenados en BD. Las reglas se aplican
por código de rol mediante middleware de rutas. Un rol activo no listado de forma
explícita no recibe acceso automático a los endpoints compartidos.

## Respuestas de autorización

- `401`: token ausente, inválido, expirado o revocado; usuario o rol inactivo.
- `403`: usuario autenticado y activo cuyo rol no permite el endpoint.
