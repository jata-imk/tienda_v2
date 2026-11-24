# Entrega para frontend: tallas y variantes con stock cero

## Resumen

El backend ahora crea las variantes correspondientes en los productos existentes cuando se
agrega una talla a su grupo. Cada variante nueva con stock cero deja constancia en el Kardex
y en auditoría. También hay un comando de backend para completar los datos anteriores.

Este repositorio contiene el backend. **No se revisó ni modificó el código del frontend**;
los puntos de este documento indican qué debe comprobar su equipo o su agente. No hay que
agregar campos nuevos al formulario para que funcionen estos cambios.

## 1. Crear una talla ahora afecta a los productos del grupo

Al llamar `POST /api/sizes`, el backend crea la talla y, para cada producto que pertenece
al grupo, crea una variante por cada color que ese producto ya tenga en alguna variante.
Las nuevas variantes tienen `stock: 0`, SKU generado por el backend y `codeBar: null`.

Por ejemplo, si un producto del grupo Adultos tiene variantes en Blanco y Azul, al agregar
una talla a Adultos se generan para ese producto las combinaciones talla nueva + Blanco y
talla nueva + Azul. No se agregan los demás colores del catálogo.

- Se incluyen productos y colores activos e inactivos. La variante generada nace inactiva
  si el producto, la talla o el color están inactivos; en otro caso nace activa.
- Un producto que no tenga ninguna variante tampoco tiene colores de donde deducir las
  combinaciones, así que no se le crea ninguna. El backend registra sus IDs en los
  metadatos de auditoría cuando esta está habilitada.
- La talla y sus variantes se crean en una transacción: si falla el proceso, no queda un
  alta parcial. La respuesta sigue devolviendo la **talla**, no la lista de productos o
  variantes creados.
- La generación automática ocurre al **crear una talla**. Crear un producto o agregarle un
  color conserva el flujo anterior: el frontend debe seguir enviando las variantes que
  quiera crear en esos casos.

**Revisar en frontend:** después de crear una talla, invalidar o recargar los datos de
productos y variantes que estén en caché o visibles, para que aparezcan las combinaciones
nuevas. Si el flujo actual crea después esas mismas combinaciones, consultar la sección 2.
No es necesario crear movimientos de inventario desde el cliente para ellas.

## 2. Reenviar una variante existente con stock cero

El endpoint `POST /api/products/{id}/variants` acepta ahora una combinación de talla y
color que ya exista si se envía con `stock: 0`. La devuelve en `data.items` sin modificar
la variante existente ni crear otro movimiento. El éxito sigue respondiendo `201`, incluso
si todas las combinaciones enviadas ya existían. La respuesta no indica cuáles se crearon y
cuáles ya existían.

Ejemplo: si la combinación `idSize: 12`, `idColor: 3` ya existe, se puede enviar:

```json
{
  "variants": [
    { "idSize": 12, "idColor": 3, "stock": 0 }
  ]
}
```

La variante devuelta conserva su **stock real**, SKU, código de barras y estado, aunque
el cliente envíe otros valores. Por ejemplo, si ya tenía 5 unidades, seguirá teniendo 5;
enviar cero no es una instrucción para vaciarla. La respuesta puede contener tanto
variantes existentes como variantes recién creadas, en el orden solicitado.

Si una combinación existente se envía con stock positivo, el endpoint responde `422`.
Tampoco se puede repetir una combinación dentro del mismo arreglo `variants`; las demás
validaciones del producto, la talla, el color y los SKU nuevos siguen aplicando. Un error
rechaza el lote completo.

**Revisar en frontend:** usar la variante devuelta por la API como fuente de verdad y no
suponer que se creó una variante o que su stock quedó en cero solo porque se envió cero.
Si se muestra un mensaje de éxito, evitar afirmar que *todas* las variantes del lote se
crearon de nuevo. Para modificar la existencia de una variante existente, seguir usando
`POST /api/inventory/movements`. `PUT /api/products/{id}` actualiza los datos base del
producto y no procesa el arreglo de variantes.

## 3. Kardex: movimientos nuevos con cantidad cero

Las variantes creadas manualmente con stock cero y las creadas automáticamente generan
un movimiento con estos campos:

```json
{
  "movementType": "entry",
  "quantity": 0,
  "previousStock": 0,
  "newStock": 0,
  "referenceType": "variant_initialization",
  "referenceId": null
}
```

La regularización histórica usa la referencia `variant_backfill`. Estos movimientos se
consultan por los endpoints existentes del Kardex y traen los demás campos habituales,
incluidos usuario, variante y fecha. No se introdujo un nuevo valor de `movementType`.
En la regularización, la fecha es la de ejecución y `idUser` es el usuario indicado por
quien ejecuta el comando; no representa necesariamente al creador original de la variante.

**Revisar en frontend:**

- Permitir que el listado y sus tipos de datos muestren `quantity: 0`, `previousStock: 0`
  y `newStock: 0`, sin ocultar la fila por considerar cero como valor vacío.
- Reconocer `variant_initialization` y `variant_backfill` si hay etiquetas, filtros o
  traducciones cerrados para `referenceType`. Pueden mostrarse como “Inicialización de
  variante” y “Regularización de variante”. No tratarlos como compra o entrada física.
- No enviar movimientos manuales de cantidad cero: `POST /api/inventory/movements` sigue
  exigiendo una cantidad mayor que cero. Estos registros los genera el backend.
- Al crear un producto o variante con stock positivo, el movimiento inicial sigue siendo
  opcional y depende de que se envíe `initialMovement`; para stock cero se genera aunque
  ese objeto no se envíe.

El dashboard ignora los movimientos de cantidad cero al decidir si una variante fue
abastecida. Por ello, una variante recién creada en cero no aparece como agotada solo por
su movimiento de inicialización; una variante con movimientos reales que llega a cero sí
puede aparecer en las alertas. No hay cambios en la forma de la respuesta del dashboard.

## 4. Cambiar el grupo de un producto o una talla

`PUT /api/products/{id}` y `PUT /api/sizes/{id}` ahora pueden responder `422` al cambiar
`idSizeGroup` si el cambio deja variantes asociadas a un grupo incompatible. El error llega
en `errors.idSizeGroup`; el mensaje explica si afecta a un producto o a una talla. Reenviar
el grupo actual no activa esta restricción.

**Revisar en frontend:** mostrar el mensaje de validación de `idSizeGroup` en el formulario
y conservar los datos capturados si la API rechaza el cambio. No asumir que mover la talla
o el producto traslada automáticamente las variantes o sus movimientos.

## 5. Datos históricos y coordinación

La regularización se ejecuta desde backend, no desde una pantalla o petición del frontend:

```bash
php artisan inventory:backfill-variants --dry-run
php artisan inventory:backfill-variants --user-id=ID_USUARIO
```

El primer comando solo diagnostica. El segundo crea combinaciones faltantes con stock cero
y registra las variantes existentes que están en cero y no tienen movimientos. No modifica
existencias anteriores y puede repetirse sin duplicar registros. Después de aplicarlo, las
consultas pueden devolver más variantes y movimientos que antes; conviene invalidar las
cachés correspondientes. La ejecución en la base destino debe coordinarse con backend.

En una copia aislada del dump del 24 de noviembre se detectaron 1,862 combinaciones
faltantes y 3,639 variantes existentes en cero sin movimientos. La aplicación y una segunda
ejecución sin duplicados se probaron en esa copia. **Esas cifras no indican que se haya
ejecutado la regularización en la base externa**, y pueden cambiar antes de aplicarla.

## 6. Comprobaciones sugeridas para frontend

1. Crear una talla en un grupo con productos de varios colores y recargar sus variantes;
   verificar que aparece una combinación en cero por cada color existente.
2. Reenviar una combinación existente con cero y confirmar que se conserva su stock real;
   enviar la misma combinación con stock positivo y comprobar el `422`.
3. Mostrar en Kardex un movimiento con cantidad cero para cada referencia nueva, con fecha
   y usuario, sin presentarlo como una entrada física de mercancía.
4. Intentar cambiar el grupo de un producto o talla que ya tenga variantes y mostrar el
   error `idSizeGroup` si el cambio resulta incompatible.
5. Tras la regularización, recargar producto y Kardex y verificar que los nuevos registros
   no producen alertas de agotamiento por sí solos.

Referencia del contrato general: [inventario.md](inventario.md) y [dashboard.md](dashboard.md).
