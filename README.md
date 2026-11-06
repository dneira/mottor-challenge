# Mottor — presupuestos de eventos

Implementación incremental del challenge con especificación primero, asistencia de IA y pruebas verificables.

## Estado actual

Cinco incrementos completos: frontend React independiente, motor monetario y seis reglas, ticket, persistencia y todos los endpoints requeridos, incluidos batch atómico y listado paginado. El caso oficial pasa también por HTTP, con números JSON exactos y snapshots recuperables. Hay caché Redis opcional de detalle y una medición local de 10.000 presupuestos sintéticos. Importación probada por el usuario en el navegador; el historial local devuelve 10.000 registros.

- `SPEC.md`: comportamiento acordado para implementar y decisiones sobre ambigüedades.
- `TASKS.md`: incrementos y pendientes.
- `docs/CHALLENGE.md`: consigna original.
- `fixtures/`: dos entradas oficiales, resultado publicado y casos compartidos del ticket.
- `api/`: Laravel existente, dominio y tests.
- `web/`: aplicación React independiente con Vite y TypeScript.
- `AI.md`: registro real de asistencia, correcciones y verificaciones.
- `docs/REDIS.md`: decisión, benchmark real y límites de la caché opcional.

## Ejecutar la API y las pruebas

Requisitos: PHP >= 8.2, Composer, extensiones intl, mbstring, PDO MySQL y PDO SQLite (SQLite >=3.35 para las pruebas); MariaDB para persistencia. Se usa brick/math 1.x, fijado por composer.lock. El dominio no necesita una base de datos.

Desde la raíz del repositorio:

```sh
cd api
composer install
# Sólo en una instalación nueva, si no existe .env:
cp .env.example .env
php artisan key:generate
php artisan test
php artisan serve --host=127.0.0.1 --port=8000
```

Si ya existe `.env`, conservarlo y omitir `cp` y `key:generate`. No sobrescribir credenciales locales. Ejecutar Artisan desde `api/`: el scaffold de Laravel 8 resuelve algunos comandos de tests desde el directorio actual.

La conexión local ya configurada usa MariaDB en Docker y TCP (`DB_HOST=127.0.0.1`, puerto publicado 3306). Al ejecutar PHP dentro de Docker, el host será el nombre del servicio correspondiente. No se versionan secretos ni el .env. Para una instalación nueva, configurar DB_DATABASE, DB_USERNAME y DB_PASSWORD antes de migrar.

Ejecutar `php artisan migrate` desde api/ para aplicar ambas migraciones del challenge y las del scaffold pendientes. Las dos migraciones de quotes ya están aplicadas en MariaDB local. La suite usa SQLite en memoria con configuración forzada y no modifica la base local.

Rutas disponibles, sin prefijo /api: GET /health, POST /quotes, POST /quotes/batch, GET /quotes y GET /quotes/{id}. Ejemplo desde api/, con el servidor en otra terminal:

```sh
curl -i http://127.0.0.1:8000/health
curl -i -H 'Content-Type: application/json' --data-binary @../fixtures/official.input.json http://127.0.0.1:8000/quotes
curl -i 'http://127.0.0.1:8000/quotes?currency=USD&page=1&per_page=50&client=norte'
```

POST guarda el presupuesto y devuelve 201 con su ID. GET devuelve el snapshot persistido o 404 JSON. Los errores de validación devuelven 422 y rutas; los errores internos no exponen trazas aunque APP_DEBUG esté activo. CORS admite GET/POST/OPTIONS y Content-Type para FRONTEND_URL (default http://localhost:5173).

Verificación adicional contra la base configurada: `php tools/smoke-api.php`. Ejecuta creación, detalle, batch y listado mediante el kernel HTTP, comprueba las dos consultas SQL y revierte sus registros. No es una prueba de red ni de navegador.

En otra terminal, desde la raíz: `cd web && npm ci && npm run dev` (Node 22). Abrir http://localhost:5173 con la API en http://127.0.0.1:8000. Para otra URL de API usar VITE_API_URL en web/.env y configurar FRONTEND_URL en api/.env acorde al origen de Vite.
  
Para probar: seleccionar **Importar histórico**, elegir `fixtures/demo.v2.json`, verificar dos presupuestos y confirmar la importación. Esto crea dos registros reales en la base configurada; repetir el archivo crea duplicados. En el histórico, buscar Agencia Norte, cambiar la moneda y abrir el detalle para editar la vista previa o imprimir. La muestra incluye los dos casos de la consigna y no sustituye el histórico real.

Tests frontend: `cd web && npm test`. Build: `cd web && npm run build`. Ver web/README.md para el manejo de errores por lote y los requisitos del navegador.

## Decisiones y diseño

- Dinero inmutable con decimal exacto. `Money::of` rechaza fracciones de centavo en entradas; `Money::rounded` expresa un punto de redondeo HALF_UP para resultados calculados.
- Cada línea se multiplica por su cantidad antes de convertirla y redondearla. Las inversas se calculan mediante racionales exactos. No se triangulan monedas.
- El reparto usa mayores restos con desempate por posición original. Conserva exactamente el total; ningún centavo se pierde.
- Las promociones redondean la suma de participaciones exactas por ítem, conservando sus restos originales. Los descuentos generales reparten un importe ya redondeado en proporción a los saldos. Son operaciones distintas y tienen pruebas que las diferencian.
- El ticket sigue el pseudocódigo normativo y cuenta grafemas con intl. `fixtures/ticket.json` se reutilizará en React.
- Las strings decimales son una representación del dominio, no una respuesta pública. ExactJson preserva los tokens numéricos, distingue números de strings y rechaza claves duplicadas. QuoteResponse emite montos numéricos con dos decimales. Los tests inspeccionan el cuerpo HTTP crudo y verifican centavos más allá de la precisión de un float.
- En un 3×2 se cuentan grupos completos por ítem: con 16 mesas se bonifican 5 y la sobrante paga precio completo. Si el IVA precede al tope, conserva su importe: el tope sólo reintegra el exceso de descuento comercial. Así cada paso explica lo ocurrido en su posición sin reescribir ajustes anteriores. Ver SPEC.md. Segundo caso esperado bajo esa política: ARS 7.832.871,49; no es un resultado publicado por el evaluador.

## Motor y extensibilidad

`QuoteCalculator` recibe ítems tipados, moneda de salida, tasas y una lista de `PricingRule`. Cada estrategia devuelve un `RuleResult` con un nuevo `CalculationState`, base y etiqueta. El motor obtiene el ajuste del cambio real de total y registra un `BreakdownStep`, incluso si el ajuste es cero. El estado protege saldos entre cero y el valor original del ítem; los impuestos se acumulan por separado.

`RuleRegistry::standard()` construye las seis estrategias desde parámetros validados. Las strings decimales son el formato interno; PrepareQuote adapta los tokens JSON exactos al dominio. Las cantidades y parámetros buy/pay son enteros.

Para agregar una regla:

1. Implementar `PricingRule::type()` y `apply(CalculationState, ExchangeRates): RuleResult`.
2. Validar su configuración en el constructor o fábrica; devolver una transición con las operaciones del estado.
3. Registrar su fábrica al componer la aplicación: `$registry = RuleRegistry::standard()->with('nuevo_tipo', $factory)`.
4. Agregar pruebas del ajuste, orden e invariantes. No editar `QuoteCalculator` ni las estrategias existentes.

`RuleRegistryTest::test_new_rule_requires_no_engine_or_existing_rule_changes` demuestra la extensión con un cargo adicional y una regla existente. El registro es inmutable y evita sobrescribir un tipo accidentalmente.

Un type desconocido produce `InvalidRuleConfiguration` con `field=type`. Los parámetros inválidos también informan el campo local. PrepareQuote prefija la ruta (por ejemplo `rules.2.type`) y el adaptador HTTP devuelve 422.

Comprobación focalizada del motor:

```sh
cd api
php artisan test --filter=QuoteCalculatorTest
```

El caso oficial produce ARS 4.722.388,00. El segundo produce ARS 7.832.871,49 según la política documentada. Además del motor, se prueban creación, ID persistido, consulta y serialización numérica HTTP.

## Redis opcional

Activar con QUOTE_CACHE_ENABLED=true en api/.env y `php artisan config:clear`, teniendo Redis accesible con las variables REDIS_HOST/PORT/PASSWORD/CACHE_DB. No hace falta cambiar CACHE_DRIVER. Si Redis falla, el detalle sigue leyendo SQL; la aplicación registra la advertencia. Desactivada por defecto.

Se cachean únicamente snapshots inmutables durante una hora. No se cachean 404 ni datos de transacciones activas. La conexión tiene timeouts de 200 ms; no se modifican sesiones ni rate limiting.

Medición local con 200 lecturas por modo: SQL p50 1,4444 ms; Redis hit 0,7258 ms (cero SQL); Redis miss 3,1602 ms. La caché aporta en lecturas repetidas, pero no se recomienda activarla por defecto sin medir la tasa de hits. Ver docs/REDIS.md para p95, metodología y límites.

Reproducir: `php artisan quotes:benchmark-cache --iterations=200`. Crea y elimina sólo su presupuesto sintético y su clave; necesita MariaDB y Redis. No representa rendimiento HTTP ni carga concurrente.

## Batch e histórico

POST /quotes/batch recibe {"quotes":[…]} con 1–500 entradas. Primero valida y calcula todas; si hay errores, devuelve rutas como quotes.2.rules.0.type sin insertar ninguna. Si todas son válidas, realiza las inserciones en una sola transacción. Devuelve 201 con imported y summaries {id,budget_id,currency,total} en el orden original. Las entradas con budget_id repetido están permitidas; los lotes anteriores exitosos no se revierten si falla otro lote.

GET /quotes usa moneda ARS, página 1 y tamaño 50 por defecto; máximo 200 por página. Devuelve IDs ascendentes, filtro por subcadena literal sin distinguir mayúsculas y metadatos enteros. Los caracteres %, _ y ! del filtro se escapan, no se interpretan como comodines. Cada total se convierte con sus tasas guardadas; si falta directa e inversa, total=null y se conserva original_total.

La proyección de listado contiene únicamente campos resumidos y total_minor (centavos como texto entero exacto). Es redundante con el total decimal y el snapshot, pero se escribe en la misma transacción y permite preservar centavos incluso en SQLite, sin cargar el detalle ni convertir números a float. La segunda migración completa registros anteriores a partir del snapshot exacto, por bloques de 200.

SQL real capturado en MariaDB por el smoke test (página 2, tamaño 1, filtro parametrizado):

```sql
select count(*) as aggregate
from `quotes`
where client_search LIKE ? ESCAPE '!';

select `id`, `budget_id`, `client`, `event`, `event_lines`,
       `currency`, `total_minor`, `exchange_rates`
from `quotes`
where client_search LIKE ? ESCAPE '!'
order by `id` asc limit 1 offset 1;
```

El binding es la subcadena normalizada y escapada entre %. Sin filtro se omite WHERE. No hay consultas por ítem ni por presupuesto; no se leen input_json ni result_json. El listado no usa Redis. Los tests cuentan exactamente estas dos consultas.

## Medición de escala sintética

`php artisan quotes:benchmark-history --quotes=10000 --samples=25` importa variaciones del caso oficial en 20 lotes de 500, mide servicios de aplicación con MariaDB real y elimina sólo sus registros generados. No mide HTTP ni navegador y no sustituye el dataset real.

Resultado local: importación 32,556 s, pico de memoria PHP 28 MB, cero registros generados remanentes. Cada caso de listado usa páginas de 50 y 25 muestras luego de un calentamiento:

| Listado | p50 | p95 | Consultas SQL |
| --- | ---: | ---: | ---: |
| Primera página ARS, filtro del conjunto generado | 11,858 ms | 12,538 ms | 2 |
| Última página USD (página 200) | 19,023 ms | 20,261 ms | 2 |
| Cliente Norte, USD (5.000 coincidencias) | 13,207 ms | 14,930 ms | 2 |

Los datos sintéticos comparten los seis ítems y reglas del caso oficial; cambian IDs y clientes. La prueba no reproduce toda la diversidad del histórico importado. No hay evidencia en esta medición para agregar caché al listado. Los scripts de benchmark usan finally para limpiar; una interrupción forzada del proceso puede impedir la limpieza.

## Evidencia y limitaciones

- `cd api && php artisan test`: 180 pruebas aprobadas (178 del challenge y 2 del scaffold).
- Las pruebas cubren precisión, redondeo, montos grandes, conversión, tasas inválidas, reparto estable, Unicode, elipsis, ejemplos oficiales del ticket, seis reglas, configuración inválida y extensibilidad.
- Se comprueban invariantes de dinero y orden en las 720 permutaciones de las seis reglas sobre un presupuesto pequeño con centavos. Los casos dirigidos verifican importes esperados, bases actualizadas, grupos incompletos, impuestos antes/después del tope, topes repetidos y ajustes cero.
- Laravel 8 y sus dependencias provienen del scaffold del usuario. Al sincronizar el lock, Composer informó 6 avisos de seguridad en 2 paquetes y dependencias abandonadas. Se registra como deuda existente: todavía no se auditó ni actualizó el framework. No es una base evaluada para producción.
- POST/GET/batch/listado verificados contra MariaDB local y rollback confirmado. Batch probado además contra un fallo SQL real mediante trigger SQLite. Redis real medido; fallos de lectura/escritura verificados mediante dobles en tests.
- El usuario confirmó la importación desde el navegador y GET /quotes informó 10.000 registros. No se cronometró esa importación ni se cotejó el archivo fila por fila. El frontend conserva los tokens numéricos con lossless-json y agrupa los importes sin pasarlos por Number.
- Tiempo efectivo de desarrollo: 4 horas.

Fuera de alcance: autenticación, deploy, edición persistente y deduplicación por budget_id. Se priorizaron cálculo reproducible, trazabilidad e importación.

Instalación comprobada en copias temporales sin dependencias preinstaladas: composer install con composer.lock (180 pruebas en SQLite); npm ci con package-lock.json (38 pruebas) y build de producción correcto.
