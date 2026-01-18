-- Eliminar lotes_grupos creados el 2026-01-18 entre las 19:00 y 20:00
DELETE FROM lotes_grupos
WHERE created_at >= '2026-01-18 19:00:00'
  AND created_at <  '2026-01-18 20:00:00';

-- Eliminar movimientos_stock con fecha_despacho = '2026-01-18 00:00:00'
DELETE FROM movimientos_stock
WHERE fecha_despacho = '2026-01-18 00:00:00';
