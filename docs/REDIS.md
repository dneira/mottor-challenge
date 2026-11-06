# Redis: decisión y medición local

Fecha: 2026-11-05. Redis 7 local en Docker y MariaDB local; extensión PhpRedis instalada. Se midió la lectura del snapshot completo a través de QuoteReader. No se midió HTTP, serialización, concurrencia ni histórico de 10.000 presupuestos.

## Decisión

Agregar caché opcional de detalle inmutable mediante cache-aside. Mantenerla desactivada por defecto hasta conocer la reutilización real. Redis sí aporta valor en detalles consultados repetidamente, pero no hay evidencia de que sea un elemento central para importar 10.000 presupuestos una vez y recorrerlos una sola vez.

MariaDB sigue siendo la fuente de verdad. El motor no depende de Redis. Los cálculos se persisten como snapshots, de modo que leer un detalle tampoco requiere recalcular aunque Redis esté desactivado.

## Resultado reproducible

Comando desde api/: `php artisan quotes:benchmark-cache --iterations=200`.

Snapshot de 2.261 bytes, 10 lecturas de calentamiento por modo y 200 muestras por modo. Lecturas secuenciales; en el modo miss se elimina únicamente la clave del presupuesto sintético antes de cada muestra, fuera del tiempo medido. No se vacía Redis globalmente ni se limpian buffers del motor SQL.

| Modo | Mediana | p95 | Consultas SQL / 200 lecturas |
| --- | ---: | ---: | ---: |
| SQL sin caché | 1,4444 ms | 1,8023 ms | 200 |
| Redis miss + SQL + escritura de caché | 3,1602 ms | 3,8904 ms | 200 |
| Redis hit | 0,7258 ms | 0,9441 ms | 0 |

Interpretación: en un hit, la mediana baja aproximadamente 50% y se elimina la consulta SQL. Un miss tarda más del doble que la lectura SQL directa en esta muestra. Por eso no se atribuye la mejora de hits a todas las peticiones. La tabla no demuestra una mejora del endpoint completo ni de la importación.

El comando crea un registro sintético identificable, compara cada lectura con el resultado original y elimina ese registro y su clave al terminar. Verifica que hits no consulten SQL; si Redis falla y se usa SQL, el benchmark no presenta ese caso como hit exitoso. Un cierre forzado del proceso podría impedir el bloque de limpieza; no usarlo como prueba de carga de producción.

## Implementación

- Configuración independiente: QUOTE_CACHE_ENABLED=false, QUOTE_CACHE_STORE=quote_redis.
- Conexión Redis dedicada quote_cache; timeout de conexión y lectura 200 ms, retry_interval=0. No cambia el cache driver global, sesiones o rate limiting.
- TTL de 3.600 segundos. Clave quotes:detail:v1:<hash de aplicación/conexión/base>:<id>.
- Miss: una consulta SQL y escritura del snapshot. Hit: cero consultas SQL de presupuestos.
- Error al leer Redis: una consulta SQL. Error al escribir Redis: devolver el resultado SQL obtenido. Registrar advertencia sin secretos. No reintentar en esa petición.
- No cachear 404 ni lecturas dentro de una transacción activa: evita publicar datos que podrían revertirse.
- Sin invalidación por importaciones porque los detalles son inmutables. Al restaurar/recrear la base y reutilizar IDs, cambiar el namespace o limpiar sólo las claves de esta aplicación. Si se agrega edición/borrado, revisar explícitamente esta política.

Laravel permite seleccionar stores independientes y TTL; el fallback a SQL está implementado por nosotros, no se presupone en el driver. Referencia: https://laravel.com/framework/docs/8.x/cache

## Qué no incorporar todavía

| Uso | Evaluación |
| --- | --- |
| Caché de listado y conteos | No incorporar por ahora: escrituras cambian páginas y conteos. En I4 se midieron 10.000 registros sintéticos con dos consultas y p95 de 12,538–20,261 ms; ver README.md. |
| Caché del cálculo | No se necesita: resultados ya persistidos y no se recalculan al leer. |
| Redis como almacenamiento del histórico | No: agrega pérdida/evicción de la fuente de verdad sin beneficio justificado. |
| Colas para el batch existente | No: cambiarían el contrato sincrónico 201 con IDs por un trabajo pendiente. |
| Locks distribuidos | No hay recurso compartido de cálculo que lo requiera. La transacción SQL protege el lote. |
| Idempotencia/reintentos de importación | Posible extensión futura, pero duplicados por budget_id están permitidos y no forman parte del alcance. |

## Qué medir antes de activarlo por defecto

Tasa de hits real, latencia de endpoint completo, consultas/segundo SQL, memoria Redis por snapshot, evicciones, latencias durante caída de Redis y carga concurrente. Si hay caídas prolongadas y mucha concurrencia, evaluar circuit breaker; actualmente cada petición puede pagar el timeout. No se implementó protección contra misses simultáneos porque la carga al cachear es una sola consulta barata; reconsiderarla si la medición lo exige.

Los tests automáticos usan el store array y dobles que fallan para verificar hit/miss, TTL configurado mediante el store, fallback y aislamiento de transacciones. El benchmark anterior verifica el recorrido con Redis real. La suite no detiene servicios del usuario.

Actualización I4: el batch sincrónico importó 10.000 presupuestos sintéticos en 32,556 s, con 28 MB de pico de memoria PHP, sin Redis. Las mediciones de listado están en README.md. Esta evidencia sostiene conservar el listado sin caché; falta medir el dataset real y concurrencia.
