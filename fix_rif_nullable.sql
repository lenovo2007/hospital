-- Script SQL para hacer el campo RIF nullable
-- Ejecutar en el servidor de producción

-- 1. Verificar estado actual de la tabla
DESCRIBE hospitales;

-- 2. Eliminar índice UNIQUE de rif (si existe)
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'hospitales' 
               AND index_name = 'hospitales_rif_unique');
SET @sqlstmt := IF(@exist > 0, 'ALTER TABLE hospitales DROP INDEX hospitales_rif_unique', 'SELECT "Index does not exist"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Hacer el campo RIF nullable (CRÍTICO)
ALTER TABLE hospitales MODIFY COLUMN rif VARCHAR(255) NULL;

-- 4. Agregar índice UNIQUE a cod_sicm (si no existe)
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'hospitales' 
               AND index_name = 'hospitales_cod_sicm_unique');
SET @sqlstmt := IF(@exist = 0, 'CREATE UNIQUE INDEX hospitales_cod_sicm_unique ON hospitales (cod_sicm)', 'SELECT "Index already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Agregar campo codigo_alt (si no existe)
SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'hospitales' 
               AND column_name = 'codigo_alt');
SET @sqlstmt := IF(@exist = 0, 'ALTER TABLE hospitales ADD COLUMN codigo_alt VARCHAR(20) NULL UNIQUE AFTER cod_sicm', 'SELECT "Column already exists"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Verificar cambios
DESCRIBE hospitales;

-- 7. Mostrar índices
SHOW INDEX FROM hospitales;
