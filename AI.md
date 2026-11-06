# Uso de IA

Registro de esta conversación y del trabajo local. No es una evaluación independiente ni una lista de prompts ficticios. El usuario pidió un plan, eligió Spec Driven Development liviano y autorizó crear specs y empezar la implementación. La IA escribió documentación, código y pruebas, ejecutó comandos y revisó los resultados. No se invocaron subagentes.

## 1. Arquitectura y decisiones de negocio

**Qué le pedí:** «Créame un plan para crear esto de la mejor forma con los mejores patrones de diseño y uso de IA donde se pueda usar», adjuntando la consigna.

**Qué me devolvió:** una propuesta de Laravel + React, motor determinista, Strategy + pipeline + registry, política monetaria, snapshot histórico y planificación por incrementos. Inicialmente propuso SQLite para facilitar la ejecución.

**Qué estaba mal o qué cambié y por qué:** el proyecto real pasó a tener MariaDB configurada y el usuario pidió resolver su conexión. La especificación final conserva MariaDB; cambiar a SQLite habría descartado trabajo ya realizado sin necesidad. Las reglas de topes e impuestos son interpretaciones documentadas, no certezas atribuidas a la consigna.

## 2. Precisión y segundo caso

**Qué le pedí:** el mismo plan debía responder al challenge adjunto, que exige decidir y explicar redondeo, conversión, grupos incompletos y orden entre impuesto y tope.

**Qué me devolvió:** HALF_UP a centavos por línea/ajuste, racionales para inversas y un total propuesto de 7.832.871,49 ARS para el segundo caso.

**Qué verifiqué:** el importe fue calculado durante la planificación con Python Decimal separado de la implementación PHP. En PHP se prueba la conversión publicada 4.722.388/1.450 = 3.256,82, el descuento de 12,5% sobre 7.416.225 = 927.028,13 y un caso que distingue convertir una línea completa de redondear cada unidad. Esto no demuestra todavía el pipeline completo: falta I2.

## 3. Dependencia decimal y compatibilidad

**Qué le pedí:** «Dale, avancemos así creemos las specs y empecemos la implementación».

**Qué me devolvió:** objetos de valor inmutables Money y ExchangeRates, un distribuidor por mayores restos y pruebas de precisión.

**Qué cambié/verifiqué y por qué:** se inspeccionó la dependencia realmente instalada antes de implementar. brick/math 1.0 requiere PHP >=8.2 y usa `RoundingMode::HalfUp`; no se copiaron constantes de ejemplos de versiones anteriores. El composer.json del scaffold declaraba PHP 7.3/8.0 y no declaraba brick/math directamente: se corrigió el requisito y se hizo explícita la dependencia usada por el dominio. Composer sincronizó el lock sin cambiar versiones de paquetes.

## 4. Ticket y errores de ejecución reales

**Qué le pedí:** empezar la implementación de la especificación aprobada; el primer incremento incluía la base del motor y ticket.

**Qué me devolvió:** traducción del pseudocódigo a PHP con grafemas y 16 fixtures compartidos, incluidos ejemplos publicados, whitespace, elipsis, acentos combinados y emoji compuesto.

**Qué estaba mal y qué se corrigió:** al generar fixtures, la IA ejecutó el script desde api/ usando una ruta relativa a la raíz; falló con FileNotFoundError. Se ejecutó desde la raíz correcta. Luego `php api/artisan test` desde la raíz falló por la resolución relativa de PHPUnit en el scaffold. Se corrigió a `cd api && php artisan test`, y ese es el comando documentado. No se ocultaron estos fallos como éxitos.

**Cómo se verificó:** fixtures literales para los ejemplos publicados y tests de ancho máximo, cantidad de renglones y ausencia de espacios al borde. La implementación TypeScript y la equivalencia entre runtimes están pendientes.

## Resultado del primer incremento

- 53 pruebas aprobadas: 51 nuevas y 2 preexistentes del scaffold.
- No hay aún pruebas del motor completo, atomicidad HTTP, rendimiento del histórico ni React. No se afirma que la entrega esté terminada.
- Las pruebas nuevas también fueron escritas por IA. Las referencias externas a la implementación son la consigna, los ejemplos publicados y el cálculo Decimal separado; se necesita revisión humana de las decisiones.
- Composer informó avisos de seguridad en dependencias del scaffold. Se documentaron; no se hizo una actualización amplia dentro del incremento monetario.

## 5. Pipeline y error de compatibilidad detectado por pruebas

**Qué le pedí:** «Continuemos», después del primer incremento. La IA delimitó la continuación al pipeline y las seis reglas de I2.

**Qué me devolvió:** estado inmutable, estrategias, registro de fábricas y un cálculo que deriva cada ajuste del cambio real de estado. Añadió comparación exacta contra el fixture oficial y pruebas de órdenes alternativos.

**Qué estaba mal:** la primera implementación de normalización decimal llamó a `BigDecimal::stripTrailingZeros()`, que no existe en la versión instalada. Doce pruebas del motor fallaron por esa llamada.

**Qué cambió y por qué:** se inspeccionó el código local de brick/math y se corrigió al método real `strippedOfTrailingZeros()`. Se volvieron a ejecutar las pruebas relevantes y luego la suite completa. Este fallo contradice cualquier supuesto de que verificar una parte de la API de una biblioteca garantiza conocer el resto.

## 6. Reparto de promociones y verificación independiente del esperado

**Qué le pedí:** continuar con las reglas respetando la especificación del incremento anterior.

**Qué me devolvió:** una operación adicional `roundShares` para repartir el descuento de una promoción a partir de las participaciones exactas, sin normalizarlas al importe agregado ya redondeado.

**Qué se verificó y por qué:** usar el repartidor proporcional anterior para este caso puede cambiar a qué ítem corresponde el centavo. Para participaciones 0.0149, 0.0048 y 0.0043, el total redondeado es 0.02: los restos originales asignan ambos centavos al primer ítem. Normalizar los pesos al total redondeado produciría otra distribución. Se agregó un test explícito, además de un caso de promoción donde redondear cada ítem por separado descontaría de más.

## Resultado del segundo incremento

- 98 pruebas aprobadas en la suite completa, sin usar la base de datos para el motor.
- Caso oficial: importes, etiquetas, bases, ítems y totales corrientes iguales al fixture publicado; total 4.722.388,00 ARS.
- Segundo caso: total 7.832.871,49 ARS y seis ajustes coincidentes con el cálculo Decimal previo.
- Las 720 permutaciones de seis reglas sobre un caso pequeño conservan dinero, respetan el orden y mantienen los saldos dentro de límites. Esto verifica invariantes; no se presenta como 720 oráculos independientes de política comercial.
- Un test registra una séptima estrategia sin modificar las existentes ni el motor.
- La primera ejecución falló y se corrigió como se registra arriba. El adaptador HTTP, persistencia, frontend e histórico real siguen pendientes; el lector exacto de fixtures es una utilidad de pruebas, no el parser JSON de producción.

## 7. API exacta y límites antes de persistir

**Qué le pedí:** «Continuemos y además analicemos la posibilidad de agregar Redis como un elemento clave en la solución por performance y ayuda real».

**Qué me devolvió:** migración quotes, validación de entrada, tokens JSON numéricos exactos, creación y detalle HTTP, snapshots y pruebas de integración.

**Qué estaba mal o se corrigió:** las primeras pruebas usaban assertContent(), inexistente en esta versión de Laravel, y un mock global de DB impedía limpiar migraciones al terminar el test. Se sustituyó por comparación explícita de getContent() y se restauró la instancia original de DB en finally. En revisión se detectó que un precio unitario enorme con cantidad cero podía pasar el límite del subtotal y fallar al serializar después de guardar; se limitó el precio antes de persistir y se agregó su regresión.

**Cómo se verificó:** comparación del resultado HTTP con el caso publicado y lectura idéntica posterior; inspección de tokens `4722388.00` y de un importe `9007199254740993.01` que no admite representación exacta como float; rechazo de strings numéricas, claves duplicadas, JSON inválido y tasas ausentes. Las pruebas usan SQLite aislado; una prueba adicional del kernel HTTP confirmó POST 201 y GET 200 en MariaDB, con rollback y conteo sin registros residuales.

## 8. Redis con evidencia, no por preferencia arquitectónica

**Qué le pedí:** analizar Redis para performance y ayuda real en la misma continuación.

**Qué me devolvió:** caché optativa sólo de detalles inmutables, TTL, namespace, conexión separada con timeout corto, fallback a SQL y benchmark reproducible. Descartó por ahora cachear listado, cálculos ya persistidos y convertir el batch sincrónico en una cola.

**Qué verificamos:** 200 muestras por modo con MariaDB y Redis locales: mediana SQL 1,4444 ms, miss 3,1602 ms y hit 0,7258 ms. El hit evita todas las consultas SQL; un miss es más caro que SQL directo. Se mantiene desactivada por defecto porque una mejora para hits no demuestra una mejora global sin conocer su frecuencia.

**Límites explícitos:** benchmark secuencial de lectura de snapshot, sin HTTP, serialización, concurrencia ni dataset completo. Tests cubren fallback ante fallos de lectura/escritura, expiración, no cachear 404 y no publicar transacciones sin confirmar. La caída real de Redis no fue provocada: se utilizaron dobles para no detener servicios del usuario.

## Resultado del tercer incremento

- 142 pruebas totales aprobadas; migración quotes aplicada y smoke test exitoso en MariaDB local.
- Caché opcional implementada y medida con Redis real. docs/REDIS.md contiene resultados y decisión.
- Batch, listado, frontend e importación real aún pendientes. Redis no se presenta como solución a problemas de importación que todavía no fueron medidos.

## 9. Batch, listado y precisión de la proyección

**Qué le pedí:** «Continuemos», y «continue» durante el trabajo de I4.

**Qué me devolvió:** preparación completa antes de escribir, una transacción por lote, errores con índice de presupuesto, listado paginado con dos consultas y conversión por tasas guardadas. Añadió total_minor como proyección exacta de lectura y migración de registros anteriores desde el snapshot.

**Qué se corrigió:** el rollback de la migración usaba dropColumn del schema builder de Laravel 8, que requería Doctrine DBAL ausente en SQLite. Las primeras 36 pruebas fallaron al limpiar migraciones. Se usó ALTER TABLE nativo, compatible con MariaDB y SQLite >=3.35 (requisito documentado). Dos expectativas adicionales comparaban enteros contra números JSON decimales con comparación estricta; se corrigieron los esperados a 0.00 y 4722388.00, manteniendo verificaciones sobre el cuerpo HTTP.

**Qué detectó la revisión:** las conversiones válidas pueden producir un número de salida con más caracteres que el límite de tokens de entrada. Se separaron esas restricciones en JsonNumber::fromInput y se agregó un caso de tasa inversa 1e-100. También se evitó tratar meta.total (conteo entero) como un monto monetario y se verificó null cuando falta una tasa.

**Cómo se verificó:** límites 500/501, orden, duplicados permitidos, errores múltiples prefijados y conservación de lotes anteriores. Un trigger SQLite provocó un fallo de almacenamiento después de una inserción válida y se comprobó rollback completo del lote. Tests verifican centavos grandes, backfill de registros anteriores, filtro Unicode, comodines literales, paginación y exactamente dos consultas sin snapshots ni Redis. Smoke test en MariaDB confirmó batch 201, listado USD, filtro literal, dos SQL y batch inválido 422 sin escrituras.

## 10. Escala medida y límites de la evidencia

**Qué le pedí:** continuar la implementación, manteniendo la evaluación previa de performance real.

**Qué me devolvió:** comando reproducible quotes:benchmark-history para importar y limpiar registros sintéticos, con medición de memoria y latencia de servicios de aplicación.

**Qué verificamos:** 10.000 variaciones del caso oficial, 20 lotes de 500, 32,556 s de importación, 28 MB de pico PHP. Listado con 25 muestras por caso: medianas de 11,858 ms (primera página ARS), 19,023 ms (última USD) y 13,207 ms (filtro Norte USD), siempre dos consultas. Se confirmó cero registros generados al terminar.

**Qué no se afirma:** no son datos del dataset.json faltante, no es una medición del navegador, de HTTP ni de concurrencia, y no demuestra el rendimiento para todos los presupuestos posibles. El listado permanece sin caché Redis.

## Resultado del cuarto incremento

- 180 pruebas aprobadas; todos los endpoints de la consigna implementados.
- Migraciones aplicadas y pruebas de integración contra MariaDB local, además de SQLite aislado.
- Frontend y validación con dataset real pendientes. No se marca la entrega completa.

## 11. Frontend e importación exacta

**Qué le pedí:** continuar con I5: importar, consultar y previsualizar desde React.

**Qué devolvió:** cliente independiente con Vite, importación por lotes, listado paginado, detalle e impresión. El navegador conserva los números del archivo con lossless-json: leer un precio como Number y reenviarlo podía perder centavos antes de que PHP lo recibiera.

**Qué verificamos:** las pruebas comprueban el cuerpo exacto del POST para 9007199254740993.01, los fixtures compartidos del ticket, la edición sin peticiones HTTP, las respuestas obsoletas y el comportamiento ante 422 y respuestas inciertas. Se comprobó el build de producción. El host del navegador integrado no estuvo disponible para inspección visual: esa revisión y la importación con el histórico real no se presentan como verificadas.
