# Incrementos y criterios de salida

- [x] Preservar consigna y extraer fixtures oficiales.
- [x] Organizar scaffold en api/ y reservar web/.
- [x] Especificar decisiones monetarias, reglas, API e histórico.
- [x] I1 — Dinero exacto, conversión, reparto y ticket PHP con tests (53 pruebas aprobadas; 51 nuevas).
- [x] I2 — Estado, registro, seis reglas y pipeline; casos oficial y segundo; órdenes alternativos (98 pruebas totales; invariantes verificadas en 720 órdenes).
- [x] I3 — Migración quotes, validación, serialización numérica exacta, health, creación y detalle; smoke test en MariaDB.
- [x] Evaluación Redis — Caché opcional de detalle, fallback SQL, pruebas y benchmark local; decisión documentada en docs/REDIS.md.
- [x] I4 — Batch atómico, listado paginado, filtros, CORS y conteo SQL; MariaDB verificada y benchmark sintético de 10.000 registros.
- [x] I5 — React: importación, listado, detalle, ticket y pruebas compartidas.
- [x] I6 — El usuario confirmó la importación en navegador; el historial local cuenta 10.000 presupuestos. Instalaciones limpias, suites y build verificados. Las mediciones de tiempo son sintéticas; no se midió la importación manual.

Cada incremento termina con revisión de diff, tests relevantes y actualización de AI.md. El próximo incremento sólo cambia decisiones documentadas de forma explícita.
