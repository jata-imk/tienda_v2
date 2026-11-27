# Entrega para frontend: validación y ordenamiento de tallas

## Resumen

El backend valida que el nombre de una talla sea único dentro de su grupo, incluso
si la talla está inactiva. Las consultas siempre ordenan primero por nombre de grupo
de A-Z; la columna elegida por el usuario ordena únicamente las tallas de cada grupo.

Este repositorio contiene el backend. **No se revisó ni modificó el código del frontend**;
los puntos de este documento indican qué debe comprobar su equipo o su agente. Se
conservan los campos de las peticiones y respuestas: no hacen falta campos nuevos
en los formularios ni cambios en los tipos de datos.

## 1. El nombre de la talla es único dentro de su grupo

`POST /api/sizes` y `PUT /api/sizes/{id}` rechazan un nombre que ya exista en el mismo
grupo. Por ejemplo, puede existir «M» en Adultos y en Niños, pero no dos tallas «M»
en Adultos. Una talla inactiva sigue reservando su nombre; se debe editar o reactivar
la existente en lugar de crear otra igual.

La edición permite conservar el nombre de la propia talla. Si se cambia únicamente
`idSizeGroup`, el backend comprueba el nombre actual en el grupo destino. También
comprueba la pareja nueva cuando se cambian nombre y grupo en una misma petición.

Un conflicto responde `422` con este formato:

```json
{
  "message": "Ya existe una talla con este nombre en el grupo.",
  "errors": {
    "name": ["Ya existe una talla con este nombre en el grupo."]
  }
}
```

La comparación conserva la collation de MariaDB: con la configuración habitual
`utf8mb4_unicode_ci`, «M» y «m» cuentan como el mismo nombre. Se mantiene el recorte
de espacios de las entradas. La restricción en base de datos protege también las
escrituras simultáneas; el conflicto sigue respondiendo `422` en `errors.name`.

**Revisar en frontend:** mostrar el mensaje de `errors.name` junto al nombre y
conservar los datos capturados si la API rechaza la operación. Este error también
puede llegar al cambiar solo el grupo. La restricción previa por variantes de otro
grupo sigue devolviendo `errors.idSizeGroup`; conservar el manejo de ambos campos.

## 2. Las consultas siempre respetan el orden por grupo

La regla aplica a `GET /api/sizes` y `POST /api/sizes/query`, antes de paginar:

| Petición | Orden de los resultados |
|---|---|
| Sin `o` | Grupo A-Z, luego `sortOrder` ascendente y luego ID ascendente |
| Con `o` | Grupo A-Z, luego columna y dirección solicitadas; empates por `sortOrder` e ID ascendentes |

Si dos grupos tienen el mismo nombre, el ID de grupo los mantiene separados antes
de ordenar sus tallas. La respuesta continúa siendo un arreglo plano en `data.items`;
no se agregaron objetos de agrupación. `data.totalCount` sigue contando tallas.

Por ejemplo, al ordenar el nombre de talla descendente, los resultados pueden ser:

| Grupo | Talla |
|---|---|
| Adultos | S |
| Adultos | M |
| Niños | S |
| Niños | M |

Los grupos siguen A-Z aunque la columna elegida vaya descendente.

**Revisar en frontend:** respetar el orden devuelto por el servidor y evitar que
una ordenación local vuelva a mezclar los grupos. El grupo es el primer criterio
fijo; el usuario cambia únicamente el criterio dentro de cada grupo. No es necesario
enviar un arreglo con dos columnas de ordenamiento.

## 3. Cambiar la columna desde el grid

Para ordenar por nombre descendente dentro de cada grupo:

```http
POST /api/sizes/query
Content-Type: application/json
Authorization: Bearer <token>
```

```json
{
  "p": { "r": 0, "s": 15 },
  "o": { "column": "name", "direction": "desc" },
  "totalCount": true
}
```

Para volver al orden de presentación, omitir `o` o enviar:

```json
{
  "o": { "column": "sortOrder", "direction": "asc" }
}
```

Se conservan `column/direction` y su formato alternativo `field/type`; GET admite
los mismos parámetros mediante query string. Se pueden ordenar columnas reales
como `name`, `sortOrder`, `status`, `id`, `createdAt` y `updatedAt`. La API recibe
estos nombres en camelCase.

`sizeGroup` es una etiqueta de la respuesta, no una columna real ordenable:
enviarla en `o.column` sigue devolviendo `422` en `errors.o.column`. Enviar
`idSizeGroup` tampoco cambia el orden fijo por nombre de grupo. Las direcciones
distintas de `asc` o `desc` siguen devolviendo `422` en `errors.o.direction`.

**Revisar en frontend:** mantener fijo el orden de Grupo y permitir cambiar el
orden de las demás columnas admitidas. Al cambiar columna o dirección, volver al
primer desplazamiento (`p.r: 0`) y consultar de nuevo. Los filtros y el formato de
paginación existentes se conservan.

## 4. Catálogos y caché

Las tallas incluidas en `data.catalogs.sizes` de `POST /api/login` y en `data.sizes`
de `GET /api/catalogs` usan el mismo orden por defecto: grupo A-Z, `sortOrder`
ascendente e ID ascendente. También incluyen el nombre del grupo en `sizeGroup`.

**Revisar en frontend:** invalidar o recargar la caché de tallas después de crear
o editar una talla, especialmente si cambia su grupo o `sortOrder`. Si se usa el
catálogo para opciones de un formulario, conservar el orden recibido al filtrar
por grupo. Las comprobaciones sobre productos y variantes tras crear una talla
siguen en [entrega-tallas-stock-cero-frontend.md](entrega-tallas-stock-cero-frontend.md).

## 5. Datos históricos y coordinación

El despliegue requiere aplicar la migración de backend:

```bash
php artisan migrate --force
```

La migración comprueba duplicados antes de crear el índice único
`(id_size_group, name)`. Si los encuentra, se detiene mostrando grupo, nombre e IDs.
La resolución debe considerar las variantes e inventario asociados; la migración
no fusiona, renombra ni elimina registros. Después de resolverlos explícitamente,
se puede volver a ejecutar el comando.

**Coordinar con backend:** confirmar que la migración está aplicada en la base
destino y refrescar los catálogos después del despliegue. La implementación y las
pruebas en bases aisladas no indican que se haya migrado la base externa.

## 6. Comprobaciones sugeridas para frontend

1. Crear una talla con un nombre ya usado en el mismo grupo, tanto activo como
   inactivo, y mostrar `errors.name` conservando la captura.
2. Crear el mismo nombre en otro grupo y editar una talla sin cambiar su propio
   nombre; ambas operaciones deben permitirse.
3. Cambiar solo el grupo hacia uno que ya tenga ese nombre y comprobar el `422`.
4. Consultar sin `o` y comprobar el orden por grupo y `sortOrder`, incluyendo los
   catálogos recibidos en login y en `GET /api/catalogs`.
5. Alternar nombre, orden y estado en ascendente y descendente; los grupos deben
   mantenerse A-Z. Cambiar de página y comprobar que no hay tallas repetidas u omitidas.
6. Aplicar filtros y cambiar el orden desde una página posterior; volver a `p.r: 0`
   y conservar el formato de resultados y el total de tallas.
7. Editar `sortOrder` o grupo, refrescar la caché y comprobar la nueva posición de
   la talla en el listado y en los selectores correspondientes.

Referencia del contrato general: [inventario.md](inventario.md).
