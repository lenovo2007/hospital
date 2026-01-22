# Hospital API — Documentación de Rutas

- Base URL (producción): `https://almacen.alwaysdata.net`
- Todas las respuestas JSON siguen el formato: `{ "status": boolean, "mensaje": string, "autenticacion": 0|1|2, "data": any }`.
  - `autenticacion`: 0=autenticado/válido, 1=token inválido o ausente, 2=token expirado.
- Codificación: UTF-8 con acentos preservados.

## Autenticación
- Proveedor: Laravel Sanctum (tokens personales)
- Flujo:
  1) `POST /api/login` con `email` y `password` → recibe `token`.
  2) Enviar `Authorization: Bearer {token}` en endpoints protegidos.
  3) `POST /api/logout` para revocar el token actual.

### Login
- Método: POST
- URL: `/api/login`
- Body (JSON):
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```
- Respuesta 200:
```json
{
  "status": true,
  "mensaje": "Login exitoso.",
  "data": {
    "token": "<TOKEN>",
    "user": { "id": 1, "email": "admin@example.com", "nombre": "Admin", "apellido": "Principal" }
  }
}
```
- Errores: HTTP 200 con `status=false` (validación o credenciales inválidas)

Ejemplos de respuestas:

- Admin
```json
{
  "status": true,
  "mensaje": "Login exitoso.",
  "data": {
    "token": "<TOKEN>",
    "user": { "id": 1, "email": "admin@example.com", "nombre": "Admin", "apellido": "Principal" }
  }
}
```

- Cliente
```json
{
  "status": true,
  "mensaje": "Login exitoso.",
  "data": {
    "token": "<TOKEN>",
    "user": { "id": 2, "email": "doctor@example.com", "nombre": "Doctor", "apellido": "Ejemplo" }
  }
}
```

#### Cuentas de ejemplo
- __Administrador__: email `admin@example.com`, password `password`.
- __Cliente__: email `doctor@example.com`, password `password`.

Puedes probar el login con cualquiera de las dos cuentas para obtener un token y consumir los endpoints protegidos.

### Logout (protegido)
- Método: POST
- URL: `/api/logout`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200:
```json
{ "status": true, "mensaje": "Sesión cerrada.", "data": null }
```

### Yo (protegido)
- Método: GET
- URL: `/api/me`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200: datos del usuario autenticado.

## Usuarios (protegido)
Modelo con campos personalizados: `tipo`, `rol`, `nombre`, `apellido`, `cedula` (único), `telefono` (opcional), `direccion` (opcional), `email`, `password`, `status` (`activo`|`inactivo`).

Nota: `status` por defecto es `activo`. Puede enviarse en creación/actualización.

### Listar usuarios
- Método: GET
- URL: `/api/users`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200:
```json
{ "status": true, "mensaje": "Listado de usuarios.", "data": { /* paginación Laravel */ } }
```

### Ver detalle
- Método: GET
- URL: `/api/users/{id}`
- Headers: `Authorization: Bearer <TOKEN>`

### Crear usuario
- Método: POST
- URL: `/api/users`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON) ejemplo:
```json
{
  "tipo": "natural",
  "rol": "admin",
  "nombre": "Ana",
  "apellido": "García",
  "cedula": "V-12345679",
  "telefono": "04140000001",
  "direccion": "Caracas",
  "email": "ana@example.com",
  "password": "secret123"
}
```
- Respuesta 200: usuario creado.

### Actualizar usuario
- Método: PUT
- URL: `/api/users/{id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Body: mismos campos (los requeridos según validación del controlador).
- Respuesta 200: usuario actualizado.

### Eliminar usuario
- Método: DELETE
- URL: `/api/users/{id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200: usuario eliminado.

## Hospitales (protegido)
Campos: `id`, `nombre`, `rif`, `email` (opcional), `telefono` (opcional), `ubicacion` (JSON opcional con `lat`,`lng`), `direccion` (opcional), `tipo` (publico/privado), `created_at`, `updated_at`, `status` (`activo`|`inactivo`).

Nota: ahora todos los recursos principales incluyen el campo `status` (`activo`|`inactivo`). Por defecto `activo`. Puede enviarse en creación/actualización.

Nota: cuando no hay resultados en el listado o el recurso solicitado no existe, se responde con HTTP 200, `status: true` y `mensaje: "hospitales no encontrado"`.

### Listar hospitales
- Método: GET
- URL: `/api/hospitales`
- Headers: `Authorization: Bearer <TOKEN>`
 - Parámetros de query opcionales:
   - `status`: `activo` | `inactivo` | `all` (por defecto `activo`).
     - `activo`: lista solo hospitales activos (comportamiento por defecto).
     - `inactivo`: lista solo hospitales inactivos.
     - `all`: lista todos (activos e inactivos).
- Respuesta 200:
```json
{ "status": true, "mensaje": "Listado de hospitales.", "data": { /* paginación */ } }
```

Ejemplos cURL:

- Solo activos (por defecto)
```bash
curl "https://almacen.alwaysdata.net/api/hospitales" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

- Inactivos
```bash
curl "https://almacen.alwaysdata.net/api/hospitales?status=inactivo" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

- Todos
```bash
curl "https://almacen.alwaysdata.net/api/hospitales?status=all" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### Ver detalle de hospital
- Método: GET
- URL: `/api/hospitales/{id}`
- Headers: `Authorization: Bearer <TOKEN>`

### Ver hospital por RIF
- Método: GET
- URL: `/api/hospitales/rif/{rif}`
- Headers: `Authorization: Bearer <TOKEN>`

Ejemplo cURL:
```bash
curl "https://almacen.alwaysdata.net/api/hospitales/rif/J-12345678-9" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

Respuestas 200:
- Encontrado
```json
{ "status": true, "mensaje": "Hospital encontrado.", "data": { /* Hospital */ } }
```
- No encontrado
```json
{ "status": false, "mensaje": "Hospital no encontrado por ese RIF.", "data": null }
```

### Crear hospital
- Método: POST
- URL: `/api/hospitales`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON) ejemplo:
```json
{
  "nombre": "Hospital Central",
  "rif": "J-12345678-9",
  "email": "contacto@hospital.test",
  "telefono": "04141234567",
  "ubicacion": { "lat": 10.491, "lng": -66.903 },
  "direccion": "Caracas",
  "tipo": "publico"
}
```
- Respuesta 200: hospital creado.

### Actualizar hospital
- Método: PUT
- URL: `/api/hospitales/{id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Body: mismos campos (según validación).
- Respuesta 200: hospital actualizado.

### Eliminar hospital
- Método: DELETE
- URL: `/api/hospitales/{id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200: hospital eliminado.

## Fichas de Insumos

### Listar fichas por hospital (público)
- Método: GET
- URL: `/api/ficha-insumos/hospital/{hospital_id}`
- Descripción: Devuelve un listado paginado de fichas de insumos que pertenecen al hospital indicado. No requiere autenticación y admite filtros opcionales.
- Parámetros:
  - `hospital_id` (path, requerido): ID numérico del hospital.
  - `insumo_id` (query, opcional): filtra por un insumo específico.
  - `status` (query, opcional): `true` o `false` para limitar por estado de la ficha.
  - `per_page` (query, opcional): número de resultados por página (por defecto 50, máximo 100).
- Respuesta 200:
```json
{
  "status": true,
  "mensaje": "Listado de fichas de insumos para el hospital.",
  "data": {
    "current_page": 1,
    "data": [ /* fichas con relaciones de hospital e insumo */ ],
    "per_page": 50,
    "total": 412
  },
  "hospital": {
    "id": 1,
    "nombre": "Almacén robotizado miranda (Jipana)",
    "cod_sicm": "23792"
  },
  "autenticacion": 1
}
```

## Inventario

### Listar inventario
- Método: GET
- URL: `/api/inventario`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200:
```json
{ "status": true, "mensaje": "Listado de inventario.", "data": { /* paginación */ } }
```

### Inventario por sede con metadatos de ingreso directo
- Método: GET
- URL: `/api/inventario/sede/{sede_id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Descripción: Devuelve los insumos agrupados por `insumo_id` para la sede indicada, incluyendo los lotes registrados en el almacén correspondiente (central, principal, farmacia, etc.) y los metadatos del ingreso directo asociado cuando el lote proviene de `ingresos_directos`.
- Ejemplo cURL:
```bash
curl "https://almacen.alwaysdata.net/api/inventario/sede/1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```
- Respuesta 200:
```json
{
  "status": true,
  "mensaje": "Inventario obtenido correctamente.",
  "data": [
    {
      "insumo_id": 125,
      "insumo": {
        "id": 125,
        "codigo": "INS-00125",
        "nombre": "Guantes de Nitrilo",
        "tipo": "medico_quirurgico",
        "unidad_medida": "caja",
        "presentacion": "100 und",
        "status": "activo"
      },
      "cantidad_total": 240,
      "lotes": [
        {
          "lote_id": 456,
          "numero_lote": "LOT-20251229-001",
          "fecha_vencimiento": "2026-12-31",
          "cantidad": 120,
          "ingreso_directo": {
            "id": 8,
            "codigo_ingreso": "ING-20251229-004",
            "tipo_ingreso": "ministerio",
            "fecha_ingreso": "2025-12-23"
          }
        },
        {
          "lote_id": 457,
          "numero_lote": "LOT-20251229-002",
          "fecha_vencimiento": "2026-10-15",
          "cantidad": 120,
          "ingreso_directo": null
        }
      ]
    }
  ]
}
```

### Ajustar inventario a cero
- Método: PUT
- URL: `/api/inventario/ajustar-cero`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON):
```json
{
  "insumo_id": 125,
  "cantidad": 0,
  "motivo": "Ajuste de inventario"
}
```
- Respuesta 200: inventario ajustado.

## Sedes (protegido)
Campos: `id`, `nombre`, `tipo`, `hospital_id` (FK hospitales.id, opcional), `status` (`activo`|`inactivo`).

Nota: `status` por defecto es `activo`. Puede enviarse en creación/actualización.

Nota: cuando no hay resultados o la sede no existe, se responde con HTTP 200, `status: true` y `mensaje: "sedes no encontrado"`.

### Listar sedes
- Método: GET
- URL: `/api/sedes`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200:
```json
{ "status": true, "mensaje": "Listado de sedes.", "data": { /* paginación */ } }
```

### Ver detalle de sede
- Método: GET
- URL: `/api/sedes/{id}`
- Headers: `Authorization: Bearer <TOKEN>`

### Crear sede
- Método: POST
- URL: `/api/sedes`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON) ejemplo:
```json
{
  "nombre": "Sede 1",
  "tipo": "almacen_principal",
  "hospital_id": 2
}
```
- Respuesta 200: sede creada.

### Actualizar sede
- Método: PUT
- URL: `/api/sedes/{id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Body: mismos campos (según validación).
- Respuesta 200: sede actualizada.

### Eliminar sede
- Método: DELETE
- URL: `/api/sedes/{id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200: sede eliminada.

## Farmacias (protegido)
Campos: `id`, `nombre`, `status` (`activo`|`inactivo`).

Nota: `status` por defecto es `activo`. Puede enviarse en creación/actualización.

## Almacenes Principales (protegido)
Campos: `id`, `insumos`, `codigo`, `numero_lote`, `fecha_vencimiento` (date), `fecha_ingreso` (date), `cantidad` (entero ≥ 0), `status` (`activo`|`inactivo`).

Nota: `status` por defecto es `activo`. Puede enviarse en creación/actualización.

## Almacenes Centrales (protegido)
Campos: `id`, `insumos`, `codigo`, `numero_lote`, `fecha_vencimiento` (date), `fecha_ingreso` (date), `cantidad` (entero ≥ 0), `status` (`activo`|`inactivo`).

Nota: `status` por defecto es `activo`. Puede enviarse en creación/actualización.

## Almacenes Farmacia (protegido)
Campos: `id`, `insumos`, `codigo`, `numero_lote`, `fecha_vencimiento` (date), `fecha_ingreso` (date), `cantidad` (entero ≥ 0), `status` (`activo`|`inactivo`).

Nota: `status` por defecto es `activo`. Puede enviarse en creación/actualización.

## Almacenes Paralelo (protegido)
Campos: `id`, `insumos`, `codigo`, `numero_lote`, `fecha_vencimiento` (date), `fecha_ingreso` (date), `cantidad` (entero ≥ 0), `status` (`activo`|`inactivo`).

Nota: `status` por defecto es `activo`. Puede enviarse en creación/actualización.

## Almacenes Servicios de Atenciones (protegido)
Campos: `id`, `insumos`, `codigo`, `numero_lote`, `fecha_vencimiento` (date), `fecha_ingreso` (date), `cantidad` (entero ≥ 0), `status` (`activo`|`inactivo`).

Nota: `status` por defecto es `activo`. Puede enviarse en creación/actualización.

## Almacenes Servicios de Apoyo (protegido)
Campos: `id`, `insumos`, `codigo`, `numero_lote`, `fecha_vencimiento` (date), `fecha_ingreso` (date), `cantidad` (entero ≥ 0), `status` (`activo`|`inactivo`).

Nota: `status` por defecto es `activo`. Puede enviarse en creación/actualización.

## Insumos (protegido)
Campos: `id`, `codigo` (único), `nombre`, `tipo`, `unidad_medida`, `cantidad_por_paquete` (entero ≥ 0), `descripcion` (opcional), `status` (`activo`|`inactivo`).

Notas:
- `status` por defecto es `activo`. Puede enviarse en creación/actualización.
- El listado soporta filtro opcional por `status` igual que hospitales.

### Listar insumos
- Método: GET
- URL: `/api/insumos`
- Headers: `Authorization: Bearer <TOKEN>`
 
- Respuesta 200:
```json
{ "status": true, "mensaje": "Listado de insumos.", "data": { /* paginación */ } }
```

Ejemplos cURL:

- Solo activos (por defecto)
```bash
curl "https://almacen.alwaysdata.net/api/insumos" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

- Inactivos
```bash
curl "https://almacen.alwaysdata.net/api/insumos?status=inactivo" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

- Todos
```bash
curl "https://almacen.alwaysdata.net/api/insumos?status=all" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### Ver detalle de insumo
- Método: GET
- URL: `/api/insumos/{id}`
- Headers: `Authorization: Bearer <TOKEN>`

### Ver por código
- Método: GET
- URL: `/api/insumos/codigo/{codigo}`
- Headers: `Authorization: Bearer <TOKEN>`
- Ejemplo cURL:
```bash
curl "https://almacen.alwaysdata.net/api/insumos/codigo/INS-001" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

### Actualizar por código
- Método: PUT
- URL: `/api/insumos/codigo/{codigo}`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON) ejemplo:
```json
{
  "nombre": "Guantes Quirúrgicos Nitrilo",
  "tipo": "descartable",
  "unidad_medida": "caja",
  "cantidad_por_paquete": 200,
  "descripcion": "Talla M",
  "status": "activo"
}
```
- Ejemplo cURL:
```bash
curl -X PUT "https://almacen.alwaysdata.net/api/insumos/codigo/INS-001" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{
    "nombre": "Guantes Quirúrgicos Nitrilo",
    "cantidad_por_paquete": 200
  }'
```

### Crear insumo
- Método: POST
- URL: `/api/insumos`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON) ejemplo:
```json
{
  "codigo": "INS-001",
  "nombre": "Guantes Quirúrgicos",
  "tipo": "descartable",
  "unidad_medida": "caja",
  "cantidad_por_paquete": 100,
  "descripcion": "Guantes de látex talla M",
  "status": "activo"
}
```
- Respuesta 200: insumo creado.

### Actualizar insumo
- Método: PUT
- URL: `/api/insumos/{id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Body: mismos campos (según validación). `codigo` debe ser único.
- Respuesta 200: insumo actualizado.

### Eliminar insumo
- Método: DELETE
- URL: `/api/insumos/{id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200: insumo eliminado.

- Nota: Crea registros en `lotes`, `ingresos_directos` y `lotes_grupos` para trazabilidad completa.

## Importación Excel

### Importar hospitales
- Método: POST
- URL: `/api/hospitales/import`
- Headers: `Authorization: Bearer <TOKEN>`
- Body: `multipart/form-data` con campo `file` (archivo .xls o .xlsx)

### Importar insumos (catálogo)
- Método: POST
- URL: `/api/insumos/import`
- Headers: `Authorization: Bearer <TOKEN>`
- Body: `multipart/form-data` con campo `file` (archivo .xls o .xlsx)
- Columnas Excel: A=CÓDIGO, B=DESCRIPCIÓN

### Importar inventario
- Método: POST
- URL: `/api/inventario/import`
- Headers: `Authorization: Bearer <TOKEN>`
- Body: `multipart/form-data` con campo `file` (archivo .xls o .xlsx)

### Importar y distribuir insumos
- Método: POST
- URL: `/api/distribucion/import`
- Headers: `Authorization: Bearer <TOKEN>`
- Body: `multipart/form-data` con campo `file` (archivo .xls o .xlsx)

### Importar insumos de hospital (inventario)
- Método: POST
- URL: `/api/hospital/insumos/import`
- Headers: `Authorization: Bearer <TOKEN>`
- Body: `multipart/form-data` con campo `file` (archivo .xls o .xlsx)
- Columnas Excel: 
  - A=id_sede, B=id_insumo, C=lote
  - D=fecha_vencimiento, E=fecha_registro, F=tipo_ingreso, G=cantidad
  - Cada fila se asigna al almacén de la sede indicada en la columna `id_sede`.
- Tipos de ingreso válidos: `ministerio`, `donacion`, `almacenado`, `adquirido`, `devolucion`, `otro`
- Respuesta 200:
```json
{
  "status": true,
  "mensaje": "Importación de insumos de hospital procesada.",
  "data": {
    "creados": 10,
    "omitidos": 0,
    "errores": 0,
    "hospital_id": 1,
    "usuario": "usuario@hospital.com"
  }
}
```
- Nota: Crea registros en `lotes`, `ingresos_directos` y `lotes_grupos` para trazabilidad completa.

## Movimientos: registrar lote real (hospital)

### Registrar lotes reales luego de recibir (protegido)
- Método: POST
- URL: `/api/movimiento/almacen/entrada/registrar-lotes-reales`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON) ejemplo:
```json
{
  "movimiento_stock_id": 123,
  "items": [
    {
      "lote_id_origen": 555,
      "cantidad": 40,
      "numero_lote": "ABC-001",
      "fecha_vencimiento": "2026-12-31"
    },
    {
      "lote_id_origen": 555,
      "cantidad": 10,
      "numero_lote": "ABC-002",
      "fecha_vencimiento": "2026-11-30"
    }
  ]
}
```

- Comportamiento:
  - El movimiento debe estar en estado `recibido` y su destino debe ser `almacenPrin`.
  - Crea (o reutiliza) el lote real en `lotes` por `(id_insumo, numero_lote, hospital_id)`.
  - Mueve stock en `almacenes_principales` desde `lote_id_origen` al lote real.
  - Si la cantidad solicitada es mayor a la disponible, se aplica lo disponible y se registra una discrepancia en `movimientos_discrepancias`.

- Respuesta 200 (ejemplo):
```json
{
  "status": true,
  "mensaje": "Lotes reales registrados y stock actualizado.",
  "data": {
    "movimiento_stock_id": 123,
    "lotes_creados": 1,
    "items_movidos": [
      {
        "lote_id_origen": 555,
        "lote_id_real": 999,
        "numero_lote": "ABC-001",
        "fecha_vencimiento": "2026-12-31",
        "cantidad": 40
      }
    ],
    "discrepancias": []
  }
}
```

## Errores (siempre HTTP 200)
- No autenticado: `{ "status": false, "mensaje": "No autenticado. Token inválido o ausente.", "data": null }`
- No autorizado: `{ "status": false, "mensaje": "No autorizado para realizar esta acción.", "data": null }`
- No encontrado: `{ "status": false, "mensaje": "Recurso no encontrado.", "data": null }`
- Validación: `{ "status": false, "mensaje": "Errores de validación.", "data": { "campo": ["mensaje"] } }`

## Headers comunes
- `Content-Type: application/json`
- `Authorization: Bearer <TOKEN>` para rutas protegidas.

## Ejemplos cURL
Login:
```bash
curl -X POST https://almacen.alwaysdata.net/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```
Con token:
```bash
curl https://almacen.alwaysdata.net/api/me \
  -H "Authorization: Bearer TOKEN"
```

## Notas
- Asegúrate de actualizar `APP_URL` en `.env`.
- Todas las rutas API están definidas en `routes/api.php` y protegidas por `auth:sanctum` salvo `/api/login`.
- Manejo de errores unificado definido en `bootstrap/app.php`.
