# INSTRUCCIONES URGENTES - Migración de Hospitales

## ⚠️ PROBLEMA ACTUAL

La importación de hospitales está fallando con 80 errores porque el campo `rif` en la base de datos **NO permite valores NULL**.

**Error típico:**
```
SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'rif' cannot be null
```

## ✅ SOLUCIÓN

Debes ejecutar la migración pendiente en el servidor de producción para hacer el campo `rif` nullable.

### **Paso 1: Conectar al Servidor**

```bash
ssh usuario@almacen.alwaysdata.net
```

### **Paso 2: Navegar al Proyecto**

```bash
cd /ruta/al/proyecto/hospital
```

### **Paso 3: Ejecutar la Migración**

```bash
php artisan migrate
```

Esta migración ejecutará:
- ✅ Eliminar índice UNIQUE de `rif`
- ✅ **Hacer el campo `rif` nullable** (permite NULL)
- ✅ Agregar índice UNIQUE a `cod_sicm` (permite NULL)
- ✅ Agregar campo `codigo_alt` con generación automática
- ✅ Generar `codigo_alt` para registros existentes

### **Paso 4: Verificar la Migración**

```bash
php artisan migrate:status
```

Deberías ver la migración `2025_10_20_000001_update_hospitales_indexes_add_codigo_alt` como **ejecutada (Ran)**.

### **Paso 5: Volver a Importar**

Una vez ejecutada la migración, vuelve a importar el archivo Excel:

```bash
curl -X POST https://almacen.alwaysdata.net/api/hospitales/import \
  -H "Authorization: Bearer TU_TOKEN" \
  -F "file=@/ruta/al/archivo/hospitales.xlsx"
```

## 📊 RESULTADOS ESPERADOS

### **Antes de la Migración:**
- ❌ 80 errores por RIF NULL
- ❌ Solo 75 hospitales creados

### **Después de la Migración:**
- ✅ 0-5 errores (solo conflictos menores)
- ✅ ~289 hospitales creados/actualizados
- ✅ RIF puede ser NULL
- ✅ `codigo_alt` generado automáticamente para todos

## 🔍 VERIFICACIÓN MANUAL (Opcional)

Si quieres verificar manualmente que el campo `rif` es nullable:

```sql
DESCRIBE hospitales;
```

Deberías ver:
```
Field: rif
Type: varchar(255)
Null: YES  ← Debe decir YES
Key: 
Default: NULL
```

## ⚡ ALTERNATIVA: Ejecutar SQL Directo

Si no puedes ejecutar `php artisan migrate`, ejecuta este SQL directamente en la base de datos:

```sql
-- Eliminar índice UNIQUE de rif
ALTER TABLE hospitales DROP INDEX hospitales_rif_unique;

-- Hacer rif nullable
ALTER TABLE hospitales MODIFY COLUMN rif VARCHAR(255) NULL;

-- Agregar índice UNIQUE a cod_sicm (permite NULL)
CREATE UNIQUE INDEX hospitales_cod_sicm_unique ON hospitales (cod_sicm);

-- Agregar campo codigo_alt
ALTER TABLE hospitales ADD COLUMN codigo_alt VARCHAR(20) NULL UNIQUE AFTER cod_sicm;
```

## 📝 NOTAS IMPORTANTES

1. **Backup**: Asegúrate de tener un backup de la base de datos antes de ejecutar la migración
2. **Downtime**: La migración debería ser rápida (< 1 minuto)
3. **Reversión**: Si algo sale mal, puedes revertir con `php artisan migrate:rollback`

## 🆘 SOPORTE

Si encuentras algún problema durante la migración, revisa:
- Permisos de la base de datos
- Conexión a la base de datos
- Logs de Laravel: `storage/logs/laravel.log`
