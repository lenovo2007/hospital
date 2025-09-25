# Proceso completo: Registrar inventario en Almacén Central y distribuir a hospitales (Esquema actualizado)

## Dónde se guarda el inventario

El inventario se registra en dos tablas:

- `lotes` (modelo `Lote`): registra el lote del insumo.
- `lotes_almacenes` (modelo `LoteAlmacen`): registra el stock por almacén de ese lote.

El endpoint que hace esto es `InventarioController::registrar()` en `app/Http/Controllers/InventarioController.php`.

- Crea un `Lote`.
- Inserta un registro en `lotes_almacenes` con `almacen_tipo` y `sede_id` (nuevo esquema: se usa `sede_id` como identificador físico del almacén). Si existe la columna `almacen_id`, se rellena con el mismo `sede_id` para compatibilidad.

## Importante: ¿Debo crear primero el Almacén Central?

Sí. Para guardar inventario en Almacén Central, debe existir previamente un registro del almacén central asociado a una sede. En el esquema actualizado, la tabla `almacenes_centrales` contiene únicamente los campos: `cantidad`, `sede_id`, `lote_id` (referencia a `lotes`), `hospital_id`, `status`, `created_at`, `updated_at`.

- En la API: puedes consultar los almacenes centrales en `GET /api/almacenes_centrales`.
- En el registro de inventario, debes enviar `almacen_tipo` y `sede_id` (no `almacen_id`).

## Proceso completo recomendado

### 1) Autenticación

- `POST /api/login` → obtén `token`.
- Todas las llamadas posteriores deben llevar `Authorization: Bearer <token>`.

### 2) Verificar/crear catálogos base

- Hospital:
- Sede:
  - `GET /api/sedes/hospital/{hospital_id}` → toma una `sede_id` (o `POST /api/sedes` para crear una, asociándola a ese hospital).
- Insumo:
  - `GET /api/insumos/{id}` o `GET /api/insumos` → toma un `insumo_id` (o `POST /api/insumos` para crear).
- Almacén Central:
  - `GET /api/almacenes_centrales` → confirma que existen registros asociados a tu `sede_id` y `hospital_id`.

  - Obtener el primer `almacen_id` disponible (cURL)

    ```
    curl -X GET "https://almacen.alwaysdata.net/api/almacenes_centrales"
{{ ... }}
- Controlador: `DistribucionInternaController::distribuir()`
- Mueve del `principal` del hospital hacia `farmacia`, `paralelo`, `servicios_apoyo`, `servicios_atenciones`, etc.

## Notas y validaciones clave

- `almacen_tipo` debe ser exactamente uno de: `almacenCent`, `almacenPrin`, `almacenFarm`, `almacenPar`, `almacenServAtenciones`, `almacenServApoyo`.
- En el nuevo esquema, NO se utiliza `almacen_id`; se usa `sede_id` como identificador físico del almacén. Para compatibilidad, si existe la columna `almacen_id` en `lotes_almacenes`, se rellena con `sede_id`.

---

## Resumen de roles y relación entre tablas

{{ ... }}
  Catálogo maestro del “qué” gestiona el sistema. Define `codigo`, `nombre`, `unidad_medida`, `tipo`, etc. No guarda cantidades.

- **Lotes (`lotes`)**  
  Identifica cada ingreso de un insumo con `numero_lote`, `fecha_vencimiento`, `fecha_ingreso`, y referencia a `insumos` via `id_insumo`.

- **Stock por almacén (`lotes_almacenes`)**  
  Registra la cantidad del lote en un almacén específico. Usa el par (`almacen_tipo`, `almacen_id`) para apuntar al almacén real y guarda `cantidad`, `hospital_id`, `sede_id`.

- **Almacenes (por tipo: `almacenes_centrales`, `almacenes_principales`, `almacenes_farmacia`, `almacenes_paralelo`, `almacenes_servicios_apoyo`, etc.)**  
  Representan el “dónde” físico. Necesitas que exista el almacén para obtener su `id` y así poder referenciarlo desde `lotes_almacenes`.  
  Tanto `almacenes_centrales`, `almacenes_principales`, `almacenes_farmacia`, `almacenes_paralelo` y `almacenes_servicios_apoyo` comparten el mismo esquema reducido: `cantidad`, `sede_id`, `lote_id`, `hospital_id`, `status` (booleano), `created_at`, `updated_at`.


### Flujo resumido

1) Crear/verificar insumo en `insumos`.

2) Crear/verificar almacén del tipo deseado (p. ej. `almacenes_centrales`) asociado a tu `sede_id`.

3) Registrar inventario vía `POST /api/inventario/registrar`:
   - Crea el `Lote` en `lotes`.
   - Crea el registro de stock en `lotes_almacenes` con `almacen_tipo` + `sede_id` y `cantidad` (y rellena `almacen_id` con el mismo valor si esa columna existe).

4) Distribuir stock cuando aplique: desde `central` a `principal` (`/api/distribucion/central` o `/api/distribucion/automatica/central`) y luego distribución interna (`/api/distribucion/principal`).

