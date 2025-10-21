# Importación de Hospitales desde Excel

## Descripción
Este documento describe cómo importar hospitales desde un archivo Excel (.xlsx) a la base de datos.

## Cambios Realizados

### 1. Estructura de Base de Datos
- **RIF**: Ahora permite valores NULL y duplicados (se eliminó restricción UNIQUE)
- **cod_sicm**: Tiene índice UNIQUE pero permite NULL
- **codigo_alt**: Nuevo campo generado automáticamente (formato: `HOSP-0001`, `HOSP-0002`, etc.)
  - Se genera automáticamente al crear un hospital (manual o por Excel)
  - Es único y secuencial
  - Sirve como identificador alternativo confiable

### 2. Lógica de Búsqueda/Actualización
Al importar desde Excel, el sistema busca hospitales existentes usando:
1. **Primera opción**: Por `cod_sicm` (si existe en el registro)
2. **Segunda opción**: Por combinación de `nombre` + `estado` + `municipio`

Si encuentra un hospital existente: **actualiza** sus datos
Si no encuentra coincidencia: **crea** un nuevo hospital

## Formato del Archivo Excel

### Estructura de Columnas (desde fila 2)
| Columna | Campo DB | Descripción | Requerido |
|---------|----------|-------------|-----------|
| A | rif | RIF del hospital | No |
| B | cod_sicm | Código SICM | No (pero recomendado para identificación única) |
| C | nombre | Nombre del hospital | **Sí** |
| D | tipo | Tipo de hospital | Sí |
| E | dependencia | Dependencia (MPPS, IVSS, etc.) | No |
| F | estado | Estado (Miranda, Caracas, etc.) | No |
| G | municipio | Municipio | No |
| H | parroquia | Parroquia | No |
| I | email | Correo electrónico del hospital | No |
| J | nombre_contacto | Nombre del director/contacto | No |
| K | email_contacto | Correo del contacto | No |
| L | telefono | Teléfono | No |
| M | direccion | Dirección completa | No |
| N | lat | Latitud (para ubicación GPS) | No |
| O | lng | Longitud (para ubicación GPS) | No |

### Notas Importantes
- La **fila 1** debe contener los encabezados
- Los datos comienzan en la **fila 2**
- El campo `nombre` es obligatorio
- Si `lat` y `lng` están presentes, se crea automáticamente el campo `ubicacion` en formato JSON

## Endpoint de Importación

### Ruta
```
POST /api/hospitales/import
```

### Autenticación
Requiere token Bearer (usuario autenticado)

### Parámetros
- **file**: Archivo Excel (.xlsx) - Máximo 10MB

### Ejemplo de Uso (cURL)
```bash
curl -X POST https://almacen.alwaysdata.net/api/hospitales/import \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  -F "file=@/ruta/al/archivo/hospitales.xlsx"
```

### Ejemplo de Uso (JavaScript/Fetch)
```javascript
const formData = new FormData();
formData.append('file', archivoExcel);

fetch('https://almacen.alwaysdata.net/api/hospitales/import', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

### Respuesta Exitosa
```json
{
  "status": true,
  "mensaje": "Importación completada.",
  "data": {
    "creados": 15,
    "actualizados": 5,
    "omitidos": 2,
    "errores": 0,
    "detalles_omitidos": [
      {
        "fila": 10,
        "motivo": "Nombre vacío"
      }
    ],
    "detalles_errores": []
  }
}
```

### Respuesta con Errores
```json
{
  "status": true,
  "mensaje": "Importación completada.",
  "data": {
    "creados": 10,
    "actualizados": 3,
    "omitidos": 1,
    "errores": 2,
    "detalles_omitidos": [],
    "detalles_errores": [
      {
        "fila": 15,
        "error": "Descripción del error"
      }
    ]
  }
}
```

## Migración de Base de Datos

Para aplicar los cambios en la base de datos, ejecutar:

```bash
php artisan migrate
```

Esto ejecutará la migración `2025_10_20_000001_update_hospitales_indexes_add_codigo_alt.php` que:
1. Elimina el índice UNIQUE de `rif`
2. Agrega índice UNIQUE a `cod_sicm` (permitiendo NULL)
3. Agrega el campo `codigo_alt`
4. Genera `codigo_alt` automáticamente para registros existentes

## Validaciones

### Extensiones PHP Requeridas
- `zip`
- `xml`
- `mbstring`

### Dependencia Composer
- `phpoffice/phpspreadsheet`

Si falta alguna dependencia, el endpoint devolverá un mensaje de error indicando qué falta.

## Ejemplo de Archivo Excel

Puedes encontrar un archivo de ejemplo en:
```
C:\wamp64\www\hospital\docs\hospitales.xlsx
```

## Creación Manual de Hospitales

Al crear un hospital manualmente mediante `POST /api/hospitales`, el campo `codigo_alt` se genera automáticamente. No es necesario enviarlo en el payload.

### Ejemplo
```json
{
  "nombre": "Hospital Central",
  "tipo": "Tipo IV",
  "rif": "J-12345678-9",
  "cod_sicm": "SICM-001",
  "estado": "Miranda",
  "municipio": "Chacao"
}
```

El sistema asignará automáticamente el siguiente `codigo_alt` disponible (ej: `HOSP-0023`).

## Troubleshooting

### Error: "Extensiones PHP requeridas no disponibles"
**Solución**: Habilitar las extensiones en `php.ini`:
```ini
extension=zip
extension=xml
extension=mbstring
```

### Error: "Dependencia faltante: phpoffice/phpspreadsheet"
**Solución**: Instalar la dependencia:
```bash
composer require phpoffice/phpspreadsheet
```

### Error: "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry"
**Solución**: Verificar que no haya duplicados en `cod_sicm` en el archivo Excel. Recuerda que `cod_sicm` debe ser único (aunque puede ser NULL).
