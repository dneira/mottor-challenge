Un problema real de nuestro producto: calcular el total de un presupuesto de evento, explicar cómo se llegó a ese número, imprimirlo y trabajar con el histórico de Comercial.
Lenguaje
PHP 8 · Laravel / React
Entrega
Repositorio + README + AI.md
Plazo
24 h
Herramientas
IA obligatoria
01
mottor.ai es la plataforma con la que las productoras de eventos diseñan, presupuestan y producen sus eventos. Un presupuesto tiene decenas de ítems en más de una moneda y, antes de llegar al cliente, pasa por una serie de condiciones comerciales: descuentos por categoría, promociones por cantidad, un tope de descuento e impuestos.

Hoy ese cálculo lo hace Comercial a mano, y cuando el cliente pregunta “¿por qué me da este número?”, nadie puede reconstruirlo. Queremos un componente que calcule el total y entregue el desglose paso a paso.
Cuatro cosas que sabemos del negocio. Primera: el orden en que se aplican las reglas lo decide quien arma el presupuesto, no el código. Segunda: la semana que viene Comercial nos va a pedir una regla nueva que hoy no conocemos. Queremos que agregarla no obligue a modificar las reglas que ya existen. Tercera: Comercial imprime el presupuesto en un ticket de ancho fijo y necesita el texto de cada evento y de cada ítem partido en renglones. Cuarta: hay que migrar el histórico —un archivo con unos 10.000 presupuestos— y poder listarlo.
02
Un presupuesto con sus ítems, el tipo de cambio del día, la moneda en la que se quiere el resultado y la lista ordenada de reglas a aplicar. Este es el caso oficial; más abajo está el resultado que esperamos para él.
```
{
  "budget": {
    "id": "PRE-2026-0418",
    "client": "Agencia Norte",
    "event": "Lanzamiento de producto · 120 personas",
    "items": [
      { "id": 1, "description": "Menú de tres pasos", "category": "catering",
        "quantity": 120, "unit_price": 18000.00, "currency": "ARS" },
      { "id": 2, "description": "Barra de tragos (módulo)", "category": "catering",
        "quantity": 3, "unit_price": 150000.00, "currency": "ARS" },
      { "id": 3, "description": "Sistema de sonido line array", "category": "sonido",
        "quantity": 1, "unit_price": 1200.00, "currency": "USD" },
      { "id": 4, "description": "Mesa redonda de 8", "category": "mobiliario",
        "quantity": 15, "unit_price": 9500.00, "currency": "ARS" },
      { "id": 5, "description": "Silla tiffany", "category": "mobiliario",
        "quantity": 120, "unit_price": 1800.00, "currency": "ARS" },
      { "id": 6, "description": "Coordinador en evento", "category": "personal",
        "quantity": 2, "unit_price": 85000.00, "currency": "ARS" }
    ]
  },
  "exchange_rates": { "USD_ARS": 1450.00 },
  "currency": "ARS",
  "rules": [
    { "type": "quantity_promo",       "category": "mobiliario", "buy": 3, "pay": 2 },
    { "type": "category_discount",    "category": "catering",   "percent": 15 },
    { "type": "percentage_discount",  "percent": 10 },
    { "type": "fixed_discount",       "amount": 50000.00, "currency": "ARS" },
    { "type": "discount_cap",         "max_percent": 20 },
    { "type": "tax",                  "name": "IVA", "percent": 21 }
  ]
}
```
Junto con este documento recibís dataset.json, con esta forma:
```
{
  "version": "v2",
  "generated_at": "2026-10-26",
  "count": 10000,
  "quotes": [
    { "budget": { "…" : "…" }, "exchange_rates": { "…" : "…" },
      "currency": "ARS", "rules": [ "…" ] }
  ]
}
```
03
Seis reglas iniciales. Cada una se aplica sobre el estado del presupuesto en ese momento de la secuencia, salvo que se indique otra base.
- Los ítems pueden estar en ARS, USD o EUR. El resultado se expresa en la moneda pedida en currency, usando los tipos de cambio recibidos. Se acepta conversión directa o inversa; no hace falta triangular monedas. exchange_rates puede traer más de un par, o venir vacío ({}) cuando no hay nada que convertir; si falta la tasa que hace falta, es un error de validación.
- El resultado final se muestra con dos decimales. Cómo representás el dinero internamente y en qué momento redondeás es una decisión tuya: tomala y explicala en el README.
- Todos los montos de la API son números JSON con dos decimales, no texto.
- Si una regla no aplica a ningún ítem, igual tiene que aparecer en el desglose, con monto cero.
Esto es lo que imprime el ticket. Los textos budget.event e items[].description se exponen además como una lista de renglones:
- Máximo 4 renglones de 28 caracteres cada uno.
- El ticket imprime en una grilla de 28 columnas: cada carácter ocupa una columna.
- Las palabras están separadas por uno o más espacios en blanco. Los espacios al inicio y al final se ignoran. En la salida, las palabras de un renglón se separan con un espacio simple.
- Las palabras se acomodan de forma voraz: una palabra va al renglón actual si entra, contando el espacio que la separa de la anterior; si no, empieza un renglón nuevo.
- Una palabra más larga que 28 se corta en trozos de 28; los trozos van en renglones sucesivos y el último trozo puede compartir renglón con las palabras siguientes.
- Si el texto no entra en 4 renglones, el cuarto se corta a 27 caracteres, sin espacios al final, y se le agrega …, que cuenta como 1 carácter.
- Un texto vacío produce una lista vacía.
Pseudocódigo normativo (W = 28, L = 4; glen es el largo en caracteres y gsub extrae caracteres):
```
words = split(trim(text), /\s+/u) sin vacíos
lines = []; cur = ""
for word in words:
    if cur != "" and glen(cur) + 1 + glen(word) <= W:
        cur = cur + " " + word
        continue
    if cur != "":
        lines.append(cur); cur = ""
    while glen(word) > W:
        lines.append(gsub(word, 0, W)); word = gsub(word, W)
    cur = word
if cur != "": lines.append(cur)
if len(lines) > L:
    lines = lines[:L]
    lines[3] = rtrim(gsub(lines[3], 0, W-1)) + "…"
return lines
```
Ejemplos:
```
"Reunión anual de accionistas · Auditorio Norte"
→ ["Reunión anual de accionistas", "· Auditorio Norte"]

"Fiesta corporativa de fin de año para toda la planta con cena de gala y show en vivo en el salón principal de Pilar Norte"
→ ["Fiesta corporativa de fin de", "año para toda la planta con",
   "cena de gala y show en vivo", "en el salón principal de…"]

"Pantalla LED 6×3 m con operador técnico"
→ ["Pantalla LED 6×3 m con", "operador técnico"]
```
04
El detalle devuelve el presupuesto, sus renglones, los totales de cada ítem en la moneda de salida y una entrada por regla, en el orden en que se aplicaron. Para el caso oficial:
```
{
  "id": 17,
  "budget_id": "PRE-2026-0418",
  "client": "Agencia Norte",
  "event": "Lanzamiento de producto · 120 personas",
  "event_lines": ["Lanzamiento de producto ·", "120 personas"],
  "currency": "ARS",
  "subtotal": 4878500.00,
  "total": 4722388.00,
  "exchange_rates": { "USD_ARS": 1450.00 },
  "items": [
    { "id": 1, "description": "Menú de tres pasos",
      "description_lines": ["Menú de tres pasos"], "category": "catering",
      "quantity": 120, "unit_price": 18000.00, "currency": "ARS",
      "line_total": 2160000.00 },
    { "id": 2, "description": "Barra de tragos (módulo)",
      "description_lines": ["Barra de tragos (módulo)"], "category": "catering",
      "quantity": 3, "unit_price": 150000.00, "currency": "ARS",
      "line_total": 450000.00 },
    { "id": 3, "description": "Sistema de sonido line array",
      "description_lines": ["Sistema de sonido line array"], "category": "sonido",
      "quantity": 1, "unit_price": 1200.00, "currency": "USD",
      "line_total": 1740000.00 },
    { "id": 4, "description": "Mesa redonda de 8",
      "description_lines": ["Mesa redonda de 8"], "category": "mobiliario",
      "quantity": 15, "unit_price": 9500.00, "currency": "ARS",
      "line_total": 142500.00 },
    { "id": 5, "description": "Silla tiffany",
      "description_lines": ["Silla tiffany"], "category": "mobiliario",
      "quantity": 120, "unit_price": 1800.00, "currency": "ARS",
      "line_total": 216000.00 },
    { "id": 6, "description": "Coordinador en evento",
      "description_lines": ["Coordinador en evento"], "category": "personal",
      "quantity": 2, "unit_price": 85000.00, "currency": "ARS",
      "line_total": 170000.00 }
  ],
  "breakdown": [
    { "step": 1, "type": "quantity_promo", "label": "3x2 en mobiliario",
      "base": 358500.00, "amount": -119500.00, "running_total": 4759000.00 },
    { "step": 2, "type": "category_discount", "label": "15% en catering",
      "base": 2610000.00, "amount": -391500.00, "running_total": 4367500.00 },
    { "step": 3, "type": "percentage_discount", "label": "10% general",
      "base": 4367500.00, "amount": -436750.00, "running_total": 3930750.00 },
    { "step": 4, "type": "fixed_discount", "label": "ARS 50.000",
      "base": 3930750.00, "amount": -50000.00, "running_total": 3880750.00 },
    { "step": 5, "type": "discount_cap", "label": "Tope 20%",
      "base": 4878500.00, "amount": 22050.00, "running_total": 3902800.00 },
    { "step": 6, "type": "tax", "label": "IVA 21%",
      "base": 3902800.00, "amount": 819588.00, "running_total": 4722388.00 }
  ]
}
```
El listado devuelve una versión resumida de cada presupuesto:
```
{
  "id": 17,
  "budget_id": "PRE-2026-0418",
  "client": "Agencia Norte",
  "event": "Lanzamiento de producto · 120 personas",
  "event_lines": ["Lanzamiento de producto ·", "120 personas"],
  "original_currency": "ARS",
  "original_total": 4722388.00,
  "currency": "USD",
  "total": 3256.82
}
```
Segundo caso, sin resultado publicado. No te damos el número: contanos en el README qué decidiste con el redondeo, la conversión, las cantidades que no completan grupo y el orden entre impuesto y tope, y por qué.
```
{
  "budget": {
    "id": "PRE-2026-0419",
    "client": "Agencia Norte",
    "event": "Lanzamiento de producto · 120 personas",
    "items": [
      { "id": 1, "description": "Menú de tres pasos", "category": "catering",
        "quantity": 120, "unit_price": 18000.00, "currency": "ARS" },
      { "id": 2, "description": "Barra de tragos (módulo)", "category": "catering",
        "quantity": 3, "unit_price": 150000.00, "currency": "ARS" },
      { "id": 3, "description": "Sistema de sonido line array", "category": "sonido",
        "quantity": 2, "unit_price": 1200.00, "currency": "USD" },
      { "id": 4, "description": "Mesa redonda de 8", "category": "mobiliario",
        "quantity": 16, "unit_price": 9500.00, "currency": "ARS" },
      { "id": 5, "description": "Silla tiffany", "category": "mobiliario",
        "quantity": 121, "unit_price": 1800.00, "currency": "ARS" },
      { "id": 6, "description": "Coordinador en evento", "category": "personal",
        "quantity": 2, "unit_price": 85000.00, "currency": "ARS" },
      { "id": 7, "description": "Pantalla LED 6×3 m con operador técnico",
        "category": "tecnica", "quantity": 1, "unit_price": 850.00, "currency": "EUR" }
    ]
  },
  "exchange_rates": { "USD_ARS": 1437.35, "EUR_ARS": 1562.10 },
  "currency": "ARS",
  "rules": [
    { "type": "quantity_promo", "category": "mobiliario", "buy": 3, "pay": 2 },
    { "type": "category_discount", "category": "catering", "percent": 15 },
    { "type": "percentage_discount", "percent": 12.5 },
    { "type": "fixed_discount", "amount": 100.00, "currency": "USD" },
    { "type": "tax", "name": "IVA", "percent": 21 },
    { "type": "discount_cap", "max_percent": 18 }
  ]
}
```
05
- PHP 8. Puede ser una librería o vivir dentro del proyecto Laravel.
- Recibe el JSON de la sección 02 y devuelve el resultado de la sección 04.
- Las reglas y su orden vienen del JSON; el código no asume una secuencia fija.
- Recordá las cuatro cosas que sabemos del negocio.
- GET /health → 200 {"status":"ok"}.
- POST /quotes: recibe un presupuesto, calcula, persiste y devuelve 201 con el detalle.
- POST /quotes/batch: recibe {"quotes":[…]}, hasta 500, y devuelve 201 {"imported": n, "quotes":[{"id","budget_id","currency","total"}]} en el mismo orden. Es atómico: si uno es inválido, devuelve 422 y no persiste ninguno.
- GET /quotes?currency=ARS&page=1&per_page=50&client=norte: currency por defecto ARS; page ≥ 1 por defecto 1; per_page entre 1 y 200, por defecto 50; client filtra por subcadena sin distinguir mayúsculas. Devuelve {"data":[…], "meta":{"page","per_page","total","last_page"}}, ordenado por id ascendente.
- GET /quotes/{id}: devuelve 200 con el detalle o 404.
- En el listado, convertí el total con las tasas guardadas de cada presupuesto; devolvé null si no hay tasa directa ni inversa.
- Errores de validación: 422 {"message":"…","errors":{"campo.ruta":["mensaje"]}}. Las respuestas de error son JSON, sin trazas.
- El front corre en otro origen: configurá CORS para GET, POST, OPTIONS y Content-Type.
- El listado se resuelve en una sola consulta SQL, más una de conteo si hace falta; mostrala en el README.
- Laravel es lo que usamos; si preferís otra cosa, decinos por qué en el README.
- React, en un proyecto independiente de la API: su propio package.json, su propio proceso y comunicación con la API por HTTP.
- Importar: seleccionar dataset.json desde el disco, enviarlo por lotes y mostrar el progreso y los errores de cada lote.
- Listado: mostrar todos los presupuestos importados con cliente, evento en sus renglones y total en la moneda elegida (ARS, USD o EUR), con filtro por cliente. Tiene que seguir siendo cómodo de usar con el histórico completo.
- Detalle: mostrar subtotal, total, ítems con sus renglones y el desglose paso a paso.
- Vista previa: en el detalle, permitir editar la descripción de un ítem y mostrar en vivo sus renglones. Calculalos en el navegador, sin llamar a la API; no hace falta guardar la edición.
- Incluí tests que corran con un comando en cada parte. El caso oficial tiene que pasar tal cual.
- En el README raíz explicá cómo correr todo y las decisiones sobre redondeo, conversión, cómo se agrega una regla, qué pasa con un type desconocido, cómo resolviste el listado del histórico y cuál es su SQL. Contá también qué quedó afuera y cuánto tiempo real te llevó.
- AI.md es obligatorio. La IA también lo es en este challenge: queremos ver tu criterio. Incluí al menos tres ejemplos concretos con el formato “qué le pedí / qué me devolvió / qué estaba mal o qué cambié y por qué”. Si la IA no se equivocó nunca, contanos qué verificaste para saberlo.
Sobre el alcance. Nos interesa más el cómo que el cuánto. Preferimos una solución clara con una parte sin terminar y explicada, que una completa que no se pueda leer. Si algo de la consigna te parece ambiguo, decidí y documentalo: también es parte de lo que miramos. Si algo no entra en el plazo, priorizá y decí qué dejaste afuera y por qué.
No hace falta: autenticación, deploy, CI, pruebas de punta a punta del front, triangulación de monedas, monedas sin centavos, edición o borrado de presupuestos ni evitar duplicados por budget_id.
06
- Plazo: 24 horas desde que recibiste el mail con este documento.
- Formato: repositorio privado en GitHub o GitLab compartido con alan@mottor.ai, o un .zip adjunto al mail de respuesta.
- Estructura: api/ y web/ en la raíz. No incluyas vendor/ ni node_modules/. Incluí composer.lock y package-lock.json, pnpm-lock.yaml o yarn.lock, según corresponda.
- Después: dentro de las 48 horas de recibida tu entrega te proponemos una llamada de 75 minutos con Alan y uno de los desarrolladores del equipo. Recorremos tu solución, la modificamos un poco en vivo —vamos a importar el histórico completo y usarlo— y conversamos. Esa llamada es la instancia técnica del proceso.