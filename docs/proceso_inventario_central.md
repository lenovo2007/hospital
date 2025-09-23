# Proceso completo: Registrar inventario en Almacén Central y distribuir a hospitales

## Dónde se guarda el inventario

El inventario se registra en dos tablas:

- `lotes` (modelo `Lote`): registra el lote del insumo.
- `lotes_almacenes` (modelo `LoteAlmacen`): registra el stock por almacén de ese lote.

El endpoint que hace esto es `InventarioController::registrar()` en `app/Http/Controllers/InventarioController.php`.

- Crea un `Lote`.
- Inserta un registro en `lotes_almacenes` con `almacen_tipo` y `almacen_id` que indicas (por ejemplo, `central` + ID del almacén central).

## Importante: ¿Debo crear primero el Almacén Central?

Sí. Para guardar inventario en Almacén Central, debe existir previamente un registro del almacén central (para obtener su `almacen_id`).

- En la API: puedes consultar los almacenes centrales en `GET /api/almacenes_centrales`.
- El `almacen_id` que envías a `/api/inventario/registrar` debe ser un ID válido devuelto por esa ruta.

## Proceso completo recomendado

### 1) Autenticación

- `POST /api/login` → obtén `token`.
- Todas las llamadas posteriores deben llevar `Authorization: Bearer <token>`.

### 2) Verificar/crear catálogos base

- Hospital:
  - `GET /api/hospitales` → toma un `hospital_id` activo (o `POST /api/hospitales` para crear uno).
- Sede:
  - `GET /api/sedes/hospital/{hospital_id}` → toma una `sede_id` (o `POST /api/sedes` para crear una, asociándola a ese hospital).
- Insumo:
  - `GET /api/insumos/{id}` o `GET /api/insumos` → toma un `insumo_id` (o `POST /api/insumos` para crear).
- Almacén Central:
  - `GET /api/almacenes_centrales` → toma un `almacen_id` (si no existe, créalo con `POST /api/almacenes_centrales` si tienes ese flujo).

  Ejemplo (Producción: https://almacen.alwaysdata.net/api)

  - Obtener el primer `almacen_id` disponible (cURL)

    ```
    curl -X GET "https://almacen.alwaysdata.net/api/almacenes_centrales"
    ```

  - Crear un Almacén Central si no existe (cURL)

    Nota: Solo si tu flujo permite crear almacenes centrales por API. Según `app/Http/Controllers/AlmacenCentralController.php`, los campos `insumos`, `codigo`, `numero_lote`, `fecha_vencimiento`, `fecha_ingreso` y `cantidad` son obligatorios (no pueden ir vacíos).

    ```bash
    curl -X POST "https://almacen.alwaysdata.net/api/almacenes_centrales" \
      -H "Authorization: Bearer TU_TOKEN" \
      -H "Content-Type: application/json" \
      -d '{
        "insumos": "SUTURA SEDA 3-0 REF (833)",
        "codigo": "CENT-001",
        "numero_lote": "CENT-SETUP-0001",
        "fecha_vencimiento": "2030-12-31",
        "fecha_ingreso": "2025-01-01",
        "cantidad": 0,
        "status": "activo"
      }'
    ```

  - Crear un Almacén Central si no existe (PowerShell)

    ```powershell
    $base = "https://almacen.alwaysdata.net/api"
    $headers = @{ Authorization = "Bearer TU_TOKEN"; "Content-Type" = "application/json" }
    $body = @{
      insumos           = "SUTURA SEDA 3-0 REF (833)"
      codigo            = "CENT-001"
      numero_lote       = "CENT-SETUP-0001"
      fecha_vencimiento = "2030-12-31"
      fecha_ingreso     = "2025-01-01"
      cantidad          = 0
      status            = "activo"
    } | ConvertTo-Json
    $nuevo = Invoke-RestMethod -Method POST -Uri "$base/almacenes_centrales" -Headers $headers -Body $body
    $nuevo.data.id
    ```

### 3) Registrar inventario en Almacén Central

- Endpoint: `POST /api/inventario/registrar`
- Controlador: `InventarioController::registrar()` valida y escribe en `lotes` y `lotes_almacenes`.

Campos obligatorios:

- `insumo_id`: ID válido en `insumos`
- `lote_cod`: string (código del lote)
- `fecha_vencimiento`: `YYYY-MM-DD`
- `almacen_tipo`: uno de `farmacia | principal | central | servicios_apoyo | servicios_atenciones`
- `almacen_id`: ID del almacén correspondiente al tipo
- `cantidad`: entero > 0
- `hospital_id`: ID válido en `hospitales`
- `sede_id`: ID válido en `sedes`

Ejemplo para Almacén Central:

```json
{
  "insumo_id": 383,
  "lote_cod": "LOTE-CENTRAL-2025-0001",
  "fecha_vencimiento": "2026-09-22",
  "almacen_tipo": "central",
  "almacen_id": 1,
  "cantidad": 1000,
  "hospital_id": 1,
  "sede_id": 1
}
```

### 4) Distribuir desde central al hospital (almacén principal)

Tienes dos opciones:

- Manual (una orden puntual):
  - Endpoint: `POST /api/distribucion/central`
  - Controlador: `DistribucionCentralController::distribuir()` en `app/Http/Controllers/DistribucionCentralController.php`
  - Envías los `lote_id` y cantidades para mover desde `central` hacia el `principal` de un hospital.

- Automática por porcentaje (según tipo de hospital configurado):
  - Endpoint: `POST /api/distribucion/automatica/central`
  - Controlador: `DistribucionAutomaticaController::distribuirPorPorcentaje()`
  - Utiliza `tipos_hospital_distribuciones` para repartir.

### 5) Distribución interna dentro del hospital

- Endpoint: `POST /api/distribucion/principal`
- Controlador: `DistribucionInternaController::distribuir()`
- Mueve del `principal` del hospital hacia `farmacia`, `paralelo`, `servicios_apoyo`, `servicios_atenciones`, etc.

## Notas y validaciones clave

- `almacen_tipo` debe ser exactamente uno de: `farmacia`, `principal`, `central`, `servicios_apoyo`, `servicios_atenciones`.
- Si envías `almacen_tipo = "almacenCent"` o similar, fallará validación.
- Debes enviar `almacen_id` del almacén correspondiente al tipo.
- En producción, la base URL es `https://almacen.alwaysdata.net/api` (sin `/public`).
- En local con WAMP, usa `http://localhost/hospital/public/api`.
