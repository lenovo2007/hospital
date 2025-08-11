# Hospital API — Documentación de Rutas

- Base URL (producción): `https://almacen.alwaysdata.net`
- Todas las respuestas JSON siguen el formato: `{ "status": boolean, "mensaje": string, "data": any }`.
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
Modelo con campos personalizados: `tipo`, `rol`, `nombre`, `apellido`, `cedula` (único), `telefono` (opcional), `direccion` (opcional), `email`, `password`.

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
Campos: `id`, `nombre`, `rif`, `lat` (nullable), `lon` (nullable), `direccion` (opcional), `tipo`.

### Listar hospitales
- Método: GET
- URL: `/api/hospitales`
- Headers: `Authorization: Bearer <TOKEN>`
- Respuesta 200:
```json
{ "status": true, "mensaje": "Listado de hospitales.", "data": { /* paginación */ } }
```

### Ver detalle de hospital
- Método: GET
- URL: `/api/hospitales/{id}`
- Headers: `Authorization: Bearer <TOKEN>`

### Crear hospital
- Método: POST
- URL: `/api/hospitales`
- Headers: `Authorization: Bearer <TOKEN>`
- Body (JSON) ejemplo:
```json
{
  "nombre": "Hospital Central",
  "rif": "J-12345678-9",
  "lat": 10.491,
  "lon": -66.903,
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

## Sedes (protegido)
Campos: `id`, `nombre`, `tipo`, `hospital_id` (nullable).

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
