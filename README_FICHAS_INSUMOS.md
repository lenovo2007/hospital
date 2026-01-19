# Sistema de Fichas de Insumos por Hospital

## 📋 Descripción

Sistema automatizado para gestionar fichas de insumos en hospitales. Cada hospital tiene una ficha completa de todos los insumos registrados en el sistema, permitiendo un control detallado del inventario.

## 🎯 Características Principales

### 1. **Generación Automática de Fichas**
- ✅ Crear fichas para un hospital específico
- ✅ Crear fichas para TODOS los hospitales
- ✅ Sincronizar nuevos insumos en todos los hospitales
- ✅ Cantidad inicial en 0 para todas las fichas

### 2. **Relaciones de Base de Datos**
```
hospitales (1) ----< (N) ficha_insumos (N) >---- (1) insumos
```

### 3. **Importación de Hospitales desde Excel**
- ✅ Manejo de RIF NULL
- ✅ Detección de emails duplicados
- ✅ Truncado de teléfonos largos
- ✅ Validación de coordenadas GPS
- ✅ Generación automática de `codigo_alt`

## 🚀 Endpoints Disponibles

### **Importación de Hospitales**

```bash
POST /api/hospitales/import
Content-Type: multipart/form-data
Authorization: Bearer {token}

# Parámetros:
file: archivo Excel (.xlsx)
```

**Formato del Excel:**
| Columna | Campo | Requerido | Notas |
|---------|-------|-----------|-------|
| A | RIF | No | Puede ser NULL o '?' |
| B | COD_SICM | No | Único si no es NULL |
| C | Nombre | Sí | Obligatorio |
| D | Tipo | No | I, II, III, IV |
| E | Dependencia | No | MPPS, IVSS, etc. |
| F | Estado | No | |
| G | Municipio | No | |
| H | Parroquia | No | |
| I | Email | No | Único, se valida duplicados |
| J | Nombre Contacto | No | |
| K | Email Contacto | No | |
| L | Teléfono | No | Máx 50 caracteres |
| M | Dirección | No | |
| N | Latitud | No | Rango: -90 a 90 |
| O | Longitud | No | Rango: -180 a 180 |

**Respuesta:**
```json
{
  "status": true,
  "mensaje": "Importación completada.",
  "data": {
    "creados": 214,
    "actualizados": 75,
    "omitidos": 2,
    "errores": 0,
    "detalles_omitidos": [
      {
        "fila": 3,
        "motivo": "Nombre vacío"
      }
    ],
    "detalles_errores": []
  }
}
```

### **Generar Fichas para un Hospital**

```bash
POST /api/ficha-insumos/generar/{hospital_id}
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
  "status": true,
  "mensaje": "Fichas de insumos generadas para el hospital.",
  "data": {
    "hospital_id": 5,
    "hospital_nombre": "Hospital Central",
    "total_insumos": 412,
    "fichas_creadas": 412,
    "fichas_existentes": 0
  }
}
```

### **Generar Fichas para TODOS los Hospitales**

```bash
POST /api/ficha-insumos/generar-todos
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
  "status": true,
  "mensaje": "Fichas de insumos generadas para todos los hospitales.",
  "data": {
    "hospitales_procesados": 289,
    "total_insumos": 412,
    "fichas_creadas": 119068,
    "fichas_existentes": 0
  }
}
```

### **Sincronizar Nuevo Insumo**

```bash
POST /api/ficha-insumos/sincronizar-insumo/{insumo_id}
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
  "status": true,
  "mensaje": "Insumo sincronizado en todos los hospitales.",
  "data": {
    "insumo_id": 413,
    "insumo_nombre": "Paracetamol 500mg",
    "hospitales_procesados": 289,
    "fichas_creadas": 289,
    "fichas_existentes": 0
  }
}
```

### **CRUD de Fichas de Insumos**

#### Listar Fichas
```bash
GET /api/ficha-insumos?hospital_id=5&per_page=50
Authorization: Bearer {token}
```

#### Crear Ficha
```bash
POST /api/ficha-insumos
Authorization: Bearer {token}
Content-Type: application/json

{
  "hospital_id": 5,
  "insumo_id": 123,
  "cantidad": 0,
  "status": true
}
```

#### Actualizar Ficha
```bash
PUT /api/ficha-insumos/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "cantidad": 50,
  "status": true
}
```

#### Eliminar Ficha
```bash
DELETE /api/ficha-insumos/{id}
Authorization: Bearer {token}
```

## 📊 Estructura de la Base de Datos

### Tabla: `ficha_insumos`

```sql
CREATE TABLE ficha_insumos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hospital_id BIGINT UNSIGNED NOT NULL,
  insumo_id BIGINT UNSIGNED NOT NULL,
  cantidad INT DEFAULT 0,
  status BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX idx_hospital_insumo (hospital_id, insumo_id)
);
```

### Tabla: `hospitales` (actualizada)

```sql
-- Campos modificados:
rif VARCHAR(255) NULL,  -- Ahora permite NULL
cod_sicm VARCHAR(100) NULL UNIQUE,  -- Único pero permite NULL
codigo_alt VARCHAR(20) NULL UNIQUE,  -- Generado automáticamente (HOSP-0001)
```

## 🔄 Flujo de Trabajo Recomendado

### 1. **Importar Hospitales**
```bash
# 1. Preparar archivo Excel con los datos
# 2. Importar hospitales
curl -X POST https://almacen.alwaysdata.net/api/hospitales/import \
  -H "Authorization: Bearer {token}" \
  -F "file=@hospitales.xlsx"
```

### 2. **Generar Fichas para Todos los Hospitales**
```bash
# Después de importar hospitales, generar fichas
curl -X POST https://almacen.alwaysdata.net/api/ficha-insumos/generar-todos \
  -H "Authorization: Bearer {token}"
```

### 3. **Cuando se Registra un Nuevo Insumo**
```bash
# 1. Crear el insumo
curl -X POST https://almacen.alwaysdata.net/api/insumos \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"nombre": "Paracetamol 500mg", "codigo": "PAR-500", ...}'

# 2. Sincronizar en todos los hospitales
curl -X POST https://almacen.alwaysdata.net/api/ficha-insumos/sincronizar-insumo/413 \
  -H "Authorization: Bearer {token}"
```

## 🛠️ Casos de Uso

### **Caso 1: Nuevo Hospital Registrado**
```bash
# Opción A: Generar fichas solo para ese hospital
POST /api/ficha-insumos/generar/5

# Opción B: Regenerar para todos (incluye el nuevo)
POST /api/ficha-insumos/generar-todos
```

### **Caso 2: Nuevo Insumo Registrado**
```bash
# Sincronizar el nuevo insumo en todos los hospitales
POST /api/ficha-insumos/sincronizar-insumo/413
```

### **Caso 3: Actualizar Cantidad de Insumo**
```bash
# Actualizar la cantidad de un insumo específico en un hospital
PUT /api/ficha-insumos/123
{
  "cantidad": 150
}
```

### **Caso 4: Consultar Fichas de un Hospital**
```bash
# Ver todas las fichas de un hospital específico
GET /api/ficha-insumos?hospital_id=5&per_page=100
```

## ⚠️ Consideraciones Importantes

### **Rendimiento**
- La generación de fichas para todos los hospitales puede tardar varios minutos
- Con 289 hospitales y 412 insumos = 119,068 fichas
- Se recomienda ejecutar en horarios de baja demanda

### **Validaciones**
- ✅ RIF puede ser NULL
- ✅ Emails duplicados se manejan automáticamente
- ✅ Teléfonos se truncan a 50 caracteres
- ✅ Coordenadas GPS se validan y limpian
- ✅ `codigo_alt` se genera automáticamente (HOSP-0001, HOSP-0002, etc.)

### **Transacciones**
- Todas las operaciones masivas usan transacciones DB
- Si falla una ficha, se continúa con las demás
- Los errores se reportan en `detalles_errores`

## 📖 Documentación OpenAPI

La documentación completa en formato OpenAPI 3.0 está disponible en:
```
docs/openapi_ficha_insumos_hospitales.yaml
```

Puedes visualizarla en:
- **Swagger Editor**: https://editor.swagger.io/
- **Postman**: Importar el archivo YAML
- **Redoc**: Generar documentación HTML

## 🧪 Ejemplos de Prueba

### **1. Importar Hospitales de Prueba**
```bash
curl -X POST http://localhost:8000/api/hospitales/import \
  -H "Authorization: Bearer 1|abc123..." \
  -F "file=@docs/hospitales.xlsx"
```

### **2. Generar Fichas para Hospital ID 1**
```bash
curl -X POST http://localhost:8000/api/ficha-insumos/generar/1 \
  -H "Authorization: Bearer 1|abc123..."
```

### **3. Listar Fichas del Hospital ID 1**
```bash
curl -X GET "http://localhost:8000/api/ficha-insumos?hospital_id=1&per_page=20" \
  -H "Authorization: Bearer 1|abc123..."
```

## 🔧 Mantenimiento

### **Regenerar Fichas**
Si necesitas regenerar todas las fichas:
```bash
# 1. Eliminar fichas existentes (opcional)
DELETE FROM ficha_insumos;

# 2. Regenerar
POST /api/ficha-insumos/generar-todos
```

### **Verificar Integridad**
```sql
-- Verificar hospitales sin fichas
SELECT h.id, h.nombre, COUNT(fi.id) as total_fichas
FROM hospitales h
LEFT JOIN ficha_insumos fi ON h.id = fi.hospital_id
WHERE h.status = 'activo'
GROUP BY h.id, h.nombre
HAVING total_fichas = 0;

-- Verificar insumos sin fichas
SELECT i.id, i.nombre, COUNT(fi.id) as total_fichas
FROM insumos i
LEFT JOIN ficha_insumos fi ON i.id = fi.insumo_id
WHERE i.status = true
GROUP BY i.id, i.nombre
HAVING total_fichas = 0;
```

## 📞 Soporte

Para reportar problemas o solicitar nuevas funcionalidades:
- Email: soporte@hospital.com
- Documentación: `/docs/openapi_ficha_insumos_hospitales.yaml`
