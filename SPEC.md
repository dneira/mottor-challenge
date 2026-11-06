# Especificación del challenge — v1

Estado: decisiones propuestas aceptadas para implementar; los cambios se documentan aquí antes de cambiar comportamiento. Fuente normativa: `docs/CHALLENGE.md`. Esta especificación distingue decisiones propias de requisitos publicados. El estado de implementación vive en `TASKS.md`.

## 1. Alcance y arquitectura

- PHP >= 8.2, Laravel existente en `api/`; React + TypeScript en `web/`, con proceso y package.json independientes. HTTP entre ambos.
- Dominio puro sin dependencias de HTTP, Eloquent ni base de datos. Estrategias para reglas, pipeline que respeta el orden, registro de tipos y objetos de valor para dinero y tasas.
- Sin autenticación, deploy, CI, edición persistida, borrado, triangulación ni deduplicación por budget_id. La edición del ticket es local.
- La IA ayuda al desarrollo y revisión. Ningún cálculo ni explicación requiere un modelo en producción.
- Se conserva Laravel 8 del scaffold del usuario durante este incremento. Actualizar el framework queda como decisión separada, no una modificación implícita del challenge.

## 2. Dinero, moneda y conversión

- Monedas admitidas: ARS, USD, EUR. Montos y tasas finitos, precios y descuentos fijos >= 0; tasas estrictamente > 0.
- Dominio: decimales/racionales exactos con brick/math, sin float. Montos monetarios ingresados admiten hasta dos decimales significativos; tasas y porcentajes conservan su precisión decimal.
- Redondeo HALF_UP a dos decimales al terminar cada total de línea convertido y cada ajuste de regla. Sumar ajustes ya redondeados no vuelve a cambiar su valor.
- Línea: convertir `quantity × unit_price` y luego redondear. No redondear el precio unitario convertido antes de multiplicar.
- Para A -> B: identidad si A=B; multiplicar por A_B cuando existe; en su ausencia dividir por B_A. La inversa se representa como racional exacto, sin aproximarla previamente. Preferir directa si llegan ambas; no triangular.
- Tasas vacías son válidas si ninguna operación necesita conversión. Una tasa requerida ausente al crear es 422; al listar, total=null para ese presupuesto.
- Persistir tasas originales. El listado convierte el total original, nunca vuelve a ejecutar reglas.
- El adaptador HTTP deberá preservar la precisión de los números de entrada y emitir montos como tokens numéricos con dos decimales, no strings. El dominio usa strings decimales internamente; no confundir ese formato con el contrato público. Prueba de respuesta HTTP cruda obligatoria.

## 3. Estado del cálculo y reglas

El estado mantiene subtotal original, saldos comerciales por ítem, impuestos acumulados y pasos. El total corriente es la suma de saldos comerciales más impuestos acumulados. Los `line_total` públicos son los importes originales convertidos, no los saldos posteriores.

Las cantidades son enteros >= 0. Los IDs de ítem son únicos dentro de un presupuesto. Se permiten ítems con cantidad/precio cero. La lista de reglas puede estar vacía. Los porcentajes de descuento y tope están entre 0 y 100; los impuestos son >= 0. Rechazar tipos desconocidos y configuraciones inválidas con la ruta específica. Una regla sin efecto siempre produce un paso de monto cero.

| Regla | Base y ajuste |
| --- | --- |
| quantity_promo | Por cada ítem de la categoría, unidades gratis = floor(quantity/buy) × (buy-pay), con buy entero > 0 y pay entero entre 0 y buy. Descontar del saldo actual la fracción unidades_gratis/quantity; cantidad cero aporta cero. Redondear el ajuste agregado de la regla. No mezclar unidades de distintos ítems. Base = suma de saldos elegibles. |
| category_discount | Base = suma de saldos actuales de la categoría. Descuento = base × percent/100. |
| percentage_discount | Base = total corriente, incluidos impuestos anteriores. Ajuste = base × percent/100, limitado al saldo comercial disponible. |
| fixed_discount | Base = total corriente. Convertir amount a moneda de salida y limitar al saldo comercial disponible. |
| discount_cap | Base = subtotal original. Descuento neto = subtotal - suma de saldos comerciales. Máximo = subtotal × max_percent/100 redondeado. Reintegrar max(0, descuento_neto - máximo). |
| tax | Base = total corriente. Agregar base × percent/100 a los impuestos acumulados. No modifica saldos comerciales. |

Decisiones propias para órdenes no ejemplificados:

- Descuentos generales y fijos se distribuyen proporcionalmente a los saldos comerciales; los de categoría sólo entre elegibles. El tope reintegra proporcionalmente a los descuentos netos por ítem.
- Distribución: calcular participaciones exactas en centavos, tomar parte entera y asignar centavos pendientes a mayores restos; empates por orden original. El ajuste agregado y sus asignaciones deben sumar exactamente lo mismo.
- Promociones calculan un descuento ideal por ítem, redondean la suma y reparten el residuo por mayores restos. Repetir una promoción aplica otra vez la fracción al saldo actual; no consume unidades para reglas futuras.
- Ningún saldo comercial puede volverse negativo. Los impuestos anteriores no se revierten por descuentos posteriores.
- El tope afecta sólo descuentos comerciales ya aplicados, excluye impuestos y no limita descuentos futuros. Repetir un tope sin descuentos intermedios no tiene efecto.
- Un impuesto anterior al tope conserva su importe: no se recalculan pasos retroactivamente. Esta es una semántica del challenge, no una política fiscal general.

Invariantes: subtotal = suma(line_total); total = subtotal + suma(amount); cada running_total = anterior + amount; cada asignación conserva el total del ajuste; ninguna regla modifica sus entradas.

## 4. Casos de aceptación monetaria

- `fixtures/official.input.json` y `official.expected.json` provienen literalmente de la consigna (sólo cambia el formato JSON). Total esperado: 4722388.00 ARS. El ID 17 es ilustrativo; lo asigna la persistencia y no se fuerza.
- Segundo caso, bajo estas decisiones: subtotal 7927225.00; promoción -119500.00; categoría -391500.00; general -927028.13; fijo -143735.00; impuesto +1332546.99; tope +154862.63; total 7832871.49. Es un resultado propio calculado independientemente, no un esperado publicado.
- Crear fixtures de órdenes alternativos, redondeos de medio centavo, grupos incompletos, saldos cero y conversiones inversas.

## 5. Ticket

- Implementar el pseudocódigo normativo literalmente: 28 caracteres, máximo 4 renglones, palabras por whitespace Unicode, espacios simples en salida, división voraz, palabras largas por trozos y truncamiento del cuarto a 27 caracteres + elipsis.
- Decisión de Unicode: un carácter es un grafema extendido. PHP intl y navegador Intl.Segmenter. Una secuencia combinada o emoji compuesto ocupa una columna según este modelo; no se mide ancho visual de fuentes.
- Entrada vacía o sólo whitespace produce []. Descripciones y evento originales se conservan.
- Fixtures comunes para PHP y TypeScript. Incluyen los tres ejemplos publicados, bordes 28/29, cuarto/quinto renglón, tabs, NBSP, tildes combinadas y emoji.
- La vista previa se calcula en navegador sin HTTP ni persistencia; impresión con fuente monoespaciada.

## 6. API y validación

Rutas exactas sin prefijo /api:

| Método y ruta | Resultado |
| --- | --- |
| GET /health | 200 {"status":"ok"} |
| POST /quotes | 201 con detalle calculado y persistido |
| POST /quotes/batch | {"quotes":[…]}, entre 1 y 500; 201 {"imported":n,"quotes":[{id,budget_id,currency,total}]} en orden |
| GET /quotes | data y meta(page,per_page,total,last_page), id ASC |
| GET /quotes/{id} | 200 detalle o 404 JSON |

- Listado: currency=ARS por defecto; page entero >=1; per_page entero 1..200, default 50; client subcadena literal sin distinguir mayúsculas. Página vacía: data=[]; total es el conteo filtrado; last_page=max(1,ceil(total/per_page)).
- Resumen: id, budget_id, client, event, event_lines, original_currency, original_total, currency solicitada y total convertido o null.
- Batch atómico por petición: validar y calcular antes de persistir; transacción para todas sus inserciones. Un fallo devuelve 422 con rutas como quotes.2.rules.0.type y no inserta nada. Los lotes previos exitosos del archivo permanecen.
- 422 {message,errors:{ruta:[mensaje]}}. JSON inválido: 422 con error de cuerpo. Errores públicos sin trazas ni secretos.
- CORS: origen del frontend configurable; GET, POST, OPTIONS y Content-Type. Sin cookies ni credenciales.
- Validar objetos/listas, tipos, monedas, tasas, porcentajes, identidad y campos requeridos. La capa de dominio vuelve a defender sus invariantes, pero no conoce rutas HTTP.

## 7. Histórico y persistencia

Mantener MariaDB ya configurada por el usuario. Guardar campos resumidos consultables, cliente normalizado, moneda, total decimal exacto, tasas, entrada original, resultado calculado y versión del motor. No recalcular histórico al leer. La proyección total_minor almacena centavos como texto entero exacto: evita la afinidad numérica de SQLite, conserva precisión en ambos motores y evita cargar snapshots en el listado. Una migración completa esa columna para registros anteriores a partir del snapshot exacto.

Listado: una consulta de datos y una de conteo. No cargar ítems ni relaciones por fila. Convertir sólo la página en memoria. SQL implementado (el SQL capturado en MariaDB está en README.md):

```sql
SELECT id, budget_id, client, event, event_lines, currency, total_minor, exchange_rates
FROM quotes
WHERE client_search LIKE ? ESCAPE '!'
ORDER BY id ASC LIMIT ? OFFSET ?;
SELECT COUNT(*) FROM quotes WHERE client_search LIKE ? ESCAPE '!';
```

Omitir WHERE sin filtro. Normalizar mayúsculas con Unicode; escapar !, % y _ como literales. No atribuir a un índice B-tree aceleración automática de `%subcadena%`. Verificar número de consultas y medir con 10.000 registros.

## 8. Frontend e importación

- React independiente: seleccionar dataset.json, validar sobre v2 y quotes, contrastar count con longitud, dividir en lotes <=500 y enviar secuencialmente.
- Mostrar progreso confirmado, lote actual, presupuestos importados y errores de cada lote. Permitir detener entre lotes.
- No reintentar automáticamente POST con resultado de red incierto: puede haber sido confirmado por el servidor. Mostrar resultado desconocido y advertir duplicación antes de reenvío manual.
- Listado con paginación del servidor, filtro debounced, moneda, estados de carga/error/vacío y descarte de respuestas obsoletas.
- Detalle con subtotal, total, ítems, pasos, ticket y edición local de descripción.

## 9. Verificación y entrega

- Un comando de tests por parte: `cd api && php artisan test`; `cd web && npm test` cuando exista el frontend.
- Pruebas de dominio, contratos HTTP, atomicidad, conteo de consultas, fixtures de ticket y componentes críticos. Sin E2E obligatorio.
- Prueba manual con dataset real y arranque desde instalación limpia. No sustituir esta evidencia por datos sintéticos sin declararlo.
- README: instalación, comandos, políticas, agregar regla, SQL real, límites, pendientes y tiempo real medido. AI.md: al menos tres ejemplos reales y verificaciones.
- No incluir vendor, node_modules, .env ni credenciales en la entrega. Incluir locks. Publicación/envío al evaluador no forma parte de esta implementación local.

## 10. Redis (evaluación solicitada durante I3)

- MariaDB sigue siendo la fuente de verdad. Caché optativa sólo del detalle inmutable, leída después de confirmar la persistencia. No participa en el cálculo ni en la atomicidad del batch.
- Cache-aside: GET busca snapshot por ID; miss consulta SQL y guarda con TTL; hit evita SQL. No cachear 404. Namespace versionado y separado por conexión/base de datos; sin invalidación global por importación.
- Desactivada por defecto; habilitar sólo si la medición de lecturas repetidas muestra beneficio. Redis caído: continuar por SQL; timeouts cortos en su conexión dedicada. No compartir la configuración de esta caché con rate limiting o sesiones.
- No cachear listado en esta fase: cada importación cambia páginas y conteos; primero implementar la consulta exigida y medir sobre 10.000 registros.
- No introducir colas para el endpoint batch: debe responder 201 con IDs y totales ya persistidos, no 202 con un trabajo pendiente.
- Registrar p50/p95 y consultas SQL de hit/miss/sin caché, diferenciando benchmark local de carga concurrente real. Evaluar memoria, tasa de hits y recuperación como trabajo posterior.

## 11. Límites del adaptador HTTP y almacenamiento (I3)

- Mantener tokens numéricos exactos al leer JSON; distinguir número de string numérica, bool y null. Profundidad JSON máxima 64. Limitar tokens numéricos de entrada a 64 caracteres y exponente absoluto <=100 antes de expandir decimales. La salida puede tener más caracteres tras una conversión válida y no hereda ese límite de entrada.
- Los IDs de ítem y cantidades deben caber en un entero PHP; cantidad >=0. Money preserva precisión arbitraria, pero precios unitarios y subtotal/total persistidos deben caber en DECIMAL(30,2), comprobado antes de insertar, incluso en ítems de cantidad cero.
- budget.id y client: texto no vacío de hasta 255 caracteres. event y description admiten texto vacío. Persistir cadenas originales sin trim automático del middleware HTTP.
- Tests de persistencia aislados en SQLite en memoria; smoke test adicional contra MariaDB local, sin sustituirlo por la suite SQLite.
