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
    "user": {
      "id": 1,
      "email": "admin@example.com",
      "nombre": "Admin",
      "apellido": "Principal",
      "is_root": true,
      "can_view": true,
      "can_create": true,
      "can_update": true,
      "can_delete": true,
      "can_crud_user": true,
      "status": "activo"
    }
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
    "user": {
      "id": 1,
      "email": "admin@example.com",
      "nombre": "Admin",
      "apellido": "Principal",
      "is_root": true,
      "can_view": true,
      "can_create": true,
      "can_update": true,
      "can_delete": true,
      "can_crud_user": true,
      "status": "activo"
    }
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
    "user": {
      "id": 2,
      "tipo": "doctor",
      "rol": "Médico",
      "email": "doctor@example.com",
      "nombre": "Doctor",
      "apellido": "Ejemplo",
      "is_root": false,
      "can_view": true,
      "can_create": false,
      "can_update": false,
      "can_delete": false,
      "can_crud_user": false,
      "status": "activo"
    }
}
```

#### Cuentas de ejemplo
- __Administrador__: 
  - Email: `admin@example.com`
  - Contraseña: `password`
  - Permisos: `is_root: true`, `can_view: true`, `can_create: true`, `can_update: true`, `can_delete: true`, `can_crud_user: true`

- __Médico (Cliente)__: 
  - Email: `doctor@example.com`
  - Contraseña: `password`
  - Permisos: `is_root: false`, `can_view: true`, `can_create: false`, `can_update: false`, `can_delete: false`, `can_crud_user: false`

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
- Respuesta 200 (ejemplo):
```json
{
  "status": true,
  "mensaje": "Datos del usuario autenticado.",
  "data": {
    "id": 1,
    "tipo": "admin",
    "rol": "Administrador",
    "nombre": "Admin",
    "apellido": "Principal",
    "email": "admin@example.com",
    "cedula": "V-12345678",
    "telefono": "04140000000",
    "direccion": "Caracas",
    "is_root": true,
    "can_view": true,
    "can_create": true,
    "can_update": true,
    "can_delete": true,
    "can_crud_user": true,
    "status": "activo",
    "created_at": "2025-01-01T00:00:00.000000Z",
    "updated_at": "2025-01-01T00:00:00.000000Z"
  }
}
```

## Usuarios (protegido)

### Modelo de Usuario

#### Campos principales
- `tipo`: Tipo de usuario (ej: 'admin', 'doctor', 'enfermero', etc.)
- `rol`: Nombre del rol del usuario
- `nombre`: Nombre del usuario
- `apellido`: Apellido del usuario
- `cedula`: Cédula de identidad (única)
- `telefono`: (opcional) Número de teléfono
- `direccion`: (opcional) Dirección física
- `email`: Correo electrónico (único)
- `password`: Contraseña (hash)
- `status`: Estado del usuario (`activo`|`inactivo`)

#### Campos de permisos (booleanos)
- `is_root`: Indica si el usuario es administrador raíz (acceso total)
- `can_view`: Permiso para ver recursos
- `can_create`: Permiso para crear recursos
- `can_update`: Permiso para actualizar recursos
- `can_delete`: Permiso para eliminar recursos
- `can_crud_user`: Permiso para gestionar usuarios (CRUD completo)

#### Campos de auditoría
- `created_at`: Fecha de creación
- `updated_at`: Última actualización
- `email_verified_at`: (opcional) Fecha de verificación de correo
- `password_reset_expires_at`: (opcional) Expiración de restablecimiento de contraseña

**Permisos requeridos para acceder a los endpoints de usuarios:**
- `can_crud_user: true` o `is_root: true` para todas las operaciones
- Permisos específicos por acción:
  - Listar/Ver: `can_view: true`
  - Crear: `can_create: true`
  - Actualizar: `can_update: true`
  - Eliminar: `can_delete: true`

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
- Respuesta 200 (ejemplo):
```json
{
  "status": true,
  "mensaje": "Usuario encontrado.",
  "data": {
    "id": 1,
    "tipo": "admin",
    "rol": "Administrador",
    "nombre": "Admin",
    "apellido": "Principal",
    "email": "admin@example.com",
    "cedula": "V-12345678",
    "telefono": "04140000000",
    "direccion": "Caracas",
    "is_root": true,
    "can_view": true,
    "can_create": true,
    "can_update": true,
    "can_delete": true,
    "can_crud_user": true,
    "status": "activo",
    "created_at": "2025-01-01T00:00:00.000000Z",
    "updated_at": "2025-01-01T00:00:00.000000Z"
  }
}
```

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
  "password": "secret123",
  "can_view": true,
  "can_create": true,
  "can_update": true,
  "can_delete": false,
  "can_crud_user": false,
  "status": "activo"
}
```
- Respuesta 200: usuario creado.

### Actualizar usuario
- Método: PUT
- URL: `/api/users/{id}`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON) ejemplo:
```json
{
  "nombre": "Ana María",
  "apellido": "González",
  "telefono": "04141234567",
  "direccion": "Nueva dirección",
  "can_view": true,
  "can_create": true,
  "can_update": true,
  "can_delete": true,
  "can_crud_user": true,
  "status": "activo"
}
```
- Respuesta 200: usuario actualizado con los nuevos valores.
- Nota: Solo usuarios con `can_crud_user: true` o `is_root: true` pueden modificar los campos de permisos.

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

### Actualizar hospital por RIF
- Método: PUT
- URL: `/api/hospitales/rif/{rif}`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON): mismos campos que update, opcionales según necesidad. Ejemplo:
```json
{
  "nombre": "Hospital Central Actualizado",
  "email": "nuevo@hospital.test",
  "telefono": "04140000000",
  "ubicacion": { "lat": 10.49, "lng": -66.90 },
  "direccion": "Nueva dirección",
  "tipo": "privado",
  "status": "activo"
}
```

Ejemplo cURL:
```bash
curl -X PUT "https://almacen.alwaysdata.net/api/hospitales/rif/J-12345678-9" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{
    "nombre":"Hospital Central Actualizado"
  }'
```

Respuestas 200:
- Actualizado
```json
{ "status": true, "mensaje": "Hospital actualizado por RIF.", "data": { /* Hospital */ } }
```
- No encontrado
```json
{ "status": false, "mensaje": "Hospital no encontrado por ese RIF.", "data": null }
```

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
