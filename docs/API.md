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
- Errores: 422 (validación), 401 (credenciales inválidas)

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
- Respuesta 201: usuario creado.

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

## Errores y Códigos
- 401 No autenticado: `{ "status": false, "mensaje": "No autenticado. Token inválido o ausente.", "data": null }`
- 403 No autorizado: `{ "status": false, "mensaje": "No autorizado para realizar esta acción.", "data": null }`
- 404 No encontrado: `{ "status": false, "mensaje": "Recurso no encontrado.", "data": null }`
- 422 Validación: `{ "status": false, "mensaje": "Errores de validación.", "data": { "campo": ["mensaje"] } }`

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
