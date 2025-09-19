# Sistema de Control de Insumos Hospitalarios

Este documento describe el diseño propuesto para controlar ingresos, salidas, transferencias, aprobaciones, conciliación y discrepancias de insumos por lote, hospital y almacén. Está alineado con el modelo actual del proyecto, que maneja múltiples tipos de almacén utilizando `almacen_tipo` + `almacen_id` (sin FK directas a una tabla única de almacenes).

## Objetivos Funcionales
- Registrar entradas (ingresos) en cada almacén por usuario responsable, con aprobación.
- Registrar salidas desde cada almacén por usuario responsable, con aprobación y validación de stock.
- Registrar transferencias entre almacenes (desde/hasta), con aprobación en origen y recepción en destino (doble aprobación opcional).
- Conciliar automáticamente: verificar que cantidades “salidas” = “llegadas” por transferencia; si difiere, registrar “discrepancias/fallos”.
- Distribuir automáticamente desde almacén central hacia hospitales según los porcentajes configurados para el tipo de hospital (`tipos_hospital_distribuciones`).
- Trazabilidad por lote (`lote_id`), insumo, hospital y almacén.

## Contexto Actual del Proyecto
- `lotes` (tabla y modelo `Lote`): define el lote por insumo y hospital.
- `lotes_almacenes` (tabla y modelo `LoteAlmacen`): define el stock por almacén, usando `almacen_tipo` + `almacen_id`.
- `tipos_hospital_distribuciones`: porcentajes por tipo de hospital (ej. Tipo 1=0.11%, ...).
- `LoteController`: CRUD de lotes + endpoints de stock por almacén sin FK a almacenes (usa `almacen_tipo`).

## Diseño de Datos (Nuevas Tablas)

1) movimientos_stock (cabecera del movimiento)
- id (PK)
- tipo: enum[entrada, salida, transferencia]
- lote_id (FK -> lotes.id)
- hospital_id (FK -> hospitales.id)
- origen_almacen_tipo (nullable en entrada externa)
- origen_almacen_id (nullable en entrada externa)
- destino_almacen_tipo (nullable en salida a consumo)
- destino_almacen_id (nullable en salida a consumo)
- cantidad_solicitada (int)
- cantidad_aprobada (int, nullable)
- cantidad_recibida (int, nullable)  // útil para transferencias
- estado: enum[borrador, pendiente_aprobacion, aprobado, rechazado, recibido, conciliado]
- solicitado_por (FK -> users.id)
- aprobado_por (FK -> users.id, nullable)
- recibido_por (FK -> users.id, nullable)
- observaciones (text, nullable)
- timestamps

2) movimientos_stock_items (opcional, si se requieren múltiples lotes por movimiento)
- id (PK)
- movimiento_id (FK -> movimientos_stock.id)
- lote_id (FK -> lotes.id)
- cantidad (int)
- timestamps

Si el caso de uso maneja un solo lote por movimiento, este detalle puede omitirse y mantener `lote_id` + `cantidad_*` en cabecera.

3) aprobaciones_movimiento (historial de decisiones)
- id (PK)
- movimiento_id (FK -> movimientos_stock.id)
- user_id (FK -> users.id)
- accion: enum[aprobar, rechazar, recibir]
- comentario (nullable)
- created_at (timestamp)

4) discrepancias_movimiento (registro de diferencias)
- id (PK)
- movimiento_id (FK -> movimientos_stock.id)
- lote_id (FK -> lotes.id)
- insumo_id (redundado para consultas rápidas)
- desde_almacen_tipo, desde_almacen_id
- hasta_almacen_tipo, hasta_almacen_id
- cantidad_salida (int)
- cantidad_llegada (int)
- diferencia (int)
- detectado_por (FK -> users.id)
- observaciones (text, nullable)
- created_at (timestamp)

5) ordenes_distribucion (opcional: agrupa distribución por porcentaje)
- id (PK)
- hospital_id (FK -> hospitales.id)
- tipo_hospital (string)
- total_asignado (int)
- estado: enum[pendiente, aplicado]
- observaciones (nullable)
- timestamps

6) ordenes_distribucion_items
- id (PK)
- orden_id (FK -> ordenes_distribucion.id)
- lote_id (FK -> lotes.id)
- cantidad_asignada (int)
- destino_almacen_tipo (string, típicamente 'principal')
- destino_almacen_id (int)
- estado: enum[pendiente, aplicado]
- timestamps

Notas:
- `almacen_tipo` + `almacen_id` son campos simples (sin FK) para compatibilidad con múltiples tablas de almacén existentes.
- Alternativa: normalizar una vista/tabla de almacenes unificada en el futuro y reintroducir FKs.

## Reglas de Negocio
- Entrada (tipo=entrada):
  - Se crea en estado `pendiente_aprobacion` con destino_almacen_*.
  - Al aprobar (usuario responsable del almacén destino): incrementar `lotes_almacenes.cantidad`.
- Salida (tipo=salida):
  - Validar stock suficiente en `lotes_almacenes` del almacén origen.
  - Al aprobar: decrementar stock en origen.
  - Destino puede ser consumo (sin recepción) o puede registrarse como transferencia si hay destino almacén.
- Transferencia (tipo=transferencia):
  - Aprobación en origen: decrementar stock origen.
  - Recepción en destino: incrementar stock destino.
  - Conciliación: comparar `cantidad_aprobada` vs `cantidad_recibida`; si difiere, registrar en `discrepancias_movimiento`.
- Conciliación: se ejecuta al recibir en destino.
- Distribución por porcentaje (desde central -> principal de cada hospital):
  - Calcular cantidades según `tipos_hospital_distribuciones`.
  - Generar movimientos de transferencia central -> principal.
  - Aprobación y recepción por cada almacén destino.

## Servicios de Stock (Sugeridos)
- StockService::incrementar(lote_id, almacen_tipo, almacen_id, cantidad)
- StockService::disminuir(lote_id, almacen_tipo, almacen_id, cantidad)  // valida disponible, lanza excepción
- StockService::transferir(lote_id, origen_tipo, origen_id, destino_tipo, destino_id, cantidad)
- Todas las operaciones se ejecutan en transacciones atómicas.

## Endpoints Propuestos (REST)
- Movimientos
  - POST `/api/movimientos`  (crear: entrada/salida/transferencia)
  - GET `/api/movimientos`   (listar por filtros: tipo, estado, hospital, almacén, lote_id, fecha)
  - GET `/api/movimientos/{id}` (detalle con items, aprobaciones, discrepancias)
  - POST `/api/movimientos/{id}/aprobar`   (aprobación en origen o destino según estado)
  - POST `/api/movimientos/{id}/rechazar`
  - POST `/api/movimientos/{id}/recibir`   (para transferencias; registra recepción y dispara conciliación)

- Discrepancias
  - GET `/api/discrepancias`
  - GET `/api/discrepancias/{id}`

- Distribución
  - POST `/api/distribucion/central/hospitales`  (genera orden + movimientos por porcentaje)
  - GET `/api/ordenes_distribucion`
  - GET `/api/ordenes_distribucion/{id}`
  - POST `/api/ordenes_distribucion/{id}/aplicar`

## Flujo Operativo
- Central carga stock (entradas) y luego distribuye por porcentaje a los hospitales (transferencias central->principal).
- Principal recibe y aprueba. Luego distribuye a Farmacia y/o Paralelo según caso.
- Paralelo puede despachar a Servicios de Apoyo; Farmacia a Servicios de Atenciones (varios tipos).
- Si un hospital no tiene Paralelo: Principal distribuye directo a Apoyo o Servicios.
- Cada movimiento requiere un usuario responsable y queda registrado con su aprobación/recepción.
- El sistema valida stock y registra discrepancias si las cantidades difieren entre origen y destino.

## Consideraciones de Seguridad y Auditoría
- Autenticación Sanctum (ya en el proyecto) + middleware de permisos.
- Validar que el usuario solo pueda operar en almacenes que le correspondan.
- Auditar `solicitado_por`, `aprobado_por`, `recibido_por`, timestamps y comentarios.

## Modelado con Lotes
- Toda la trazabilidad se hace por `lote_id` para respetar vencimientos, ingresos, etc.
- Posible consolidación por insumo en reportes, pero las operaciones actualizan stock por lote.

## Próximos Pasos (Implementación por Fases)
1) Migraciones + Modelos: `movimientos_stock`, `aprobaciones_movimiento`, `discrepancias_movimiento` (opcional: `movimientos_stock_items`, `ordenes_distribucion*`).
2) Servicios de Stock (incrementar, disminuir, transferir) con transacciones.
3) Controladores y Rutas para movimientos, aprobaciones, recepción y discrepancias.
4) Endpoints de distribución por porcentaje (central -> principal).
5) Documentación OpenAPI (schemas y paths) y ejemplos de requests/responses.
6) Validaciones de permisos por almacén y roles.

## Notas Técnicas
- `almacen_tipo` ejemplos sugeridos: `central`, `principal`, `farmacia`, `paralelo`, `servicios_apoyo`, `servicios_atenciones`.
- `lotes_almacenes` ya usa índice único (`lote_id`, `almacen_tipo`, `almacen_id`) para evitar duplicados por almacén.
- Donde existan tablas separadas por tipo de almacén (farmacia, paralelo, etc.), se referencia con `almacen_tipo` + `almacen_id` en movimientos y en stock.

---
Este diseño es compatible con el estado actual del repositorio y permite escalar a nuevos tipos de almacén o reglas sin romper la compatibilidad. Se puede extender con reportes (por ejemplo: trazabilidad de lote, kardex por almacén, indicadores de discrepancias por usuario).
