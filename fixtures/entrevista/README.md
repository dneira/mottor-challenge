# Ensayo de entrevista · 10 minutos

Abrí «Importar histórico» y seleccioná **01-recorrido.v2.json**. El archivo contiene seis presupuestos sintéticos; el primero reproduce las cifras oficiales con otro cliente e identificador para poder encontrarlo. Importalo una sola vez en tu base de demostración: volver a importar crea duplicados. En el historial elegí ARS y buscá **Laboratorio Entrevista**. Los IDs numéricos del detalle los asigna tu base; usá los códigos ENT para orientarte.

| Abrí | Pregunta para hacer antes de mostrar el detalle | Qué mostrar y cómo defenderlo |
|---|---|---|
| ENT-01 | ¿Por qué un «descuento» suma dinero? | El subtotal es ARS 4.878.500. Los primeros cuatro descuentos suman 997.750; el máximo del 20 % es 975.700. El tope **devuelve 22.050**. Sobre la base de 3.902.800 se agregan 819.588 de IVA: total **4.722.388,00**. Señalá esos últimos dos renglones. |
| ENT-02 y ENT-03 | ¿Qué cambia si muevo el IVA una posición? | Misma compra de 100 y descuento de 30. Con tope antes del IVA: 70 → 80 → **96,80**. Con IVA antes del tope: 70 → 84,70 → **94,70**. El reintegro es 10 en ambos; lo que cambia es la base del IVA. Ningún paso reescribe un impuesto anterior. |
| ENT-04 | ¿Siete unidades en 3×2 dan dos gratis? | Hay tres ítems distintos de 2, 1 y 4 unidades a 100. Los grupos se forman por ítem: sólo el tercero completa uno. Subtotal 700, promoción −100, total **600,00**. El descuento de catering aparece en **0,00** porque no hay ítems de esa categoría. |
| ENT-05 | ¿Tres cables de USD 0,01 a 1,50 ARS/USD dan 5 o 6 centavos? | Primero se multiplica la cantidad: USD 0,03 × 1,50 = ARS 0,045; se redondea la línea a **ARS 0,05**. Redondear cada unidad antes de multiplicar daría 0,06. |
| ENT-06 | ¿Qué se puede convertir con una sola tasa? | EUR 15 con ARS_EUR = 0,01: para pasar a ARS se divide por 0,01, total **ARS 1.500,00**. En el listado cambiá a EUR: **15,00**. Cambiá a USD: sin conversión disponible. Compará con ENT-01, que sí muestra **USD 3.256,82**. El detalle original permanece en ARS. |

**Ticket (un minuto).** En ENT-06 abrí la vista previa: «Pantalla LED 6×3 m con» y «operador técnico» ocupan dos líneas. Reemplazá la descripción por cinco palabras de 28 letras (copiá esta línea):

~~~text
aaaaaaaaaaaaaaaaaaaaaaaaaaaa bbbbbbbbbbbbbbbbbbbbbbbbbbbb cccccccccccccccccccccccccccc dddddddddddddddddddddddddddd eeeeeeeeeeeeeeeeeeeeeeeeeeee
~~~

Vas a ver cuatro renglones; el cuarto termina con 27 letras d y una elipsis. Cerrá y reabrí el detalle: vuelve la descripción original. La edición sólo afecta la vista previa.

**Rechazo (un minuto).** Importá **02-rechazo.v2.json**. El primer presupuesto es válido; el segundo pide la regla inexistente descuento_por_umbral. Esperá un 422 en `quotes.1.rules.0.type` (segundo presupuesto, primera regla). Buscá **Control de rechazo**: no debe aparecer ninguna fila en una base que no tuviera antes ese cliente. Los seis del primer archivo siguen disponibles. La transacción protege el lote; los lotes confirmados antes no se revierten.

**Si te proponen extender el motor.** Usá ese rechazo como punto de partida: antes de implementar una regla por umbral, acordá moneda, base y si se aplica en igualdad. Para un descuento de 10.000 cuando el total corriente alcanza 100.000, probá 99.999,99 (no aplica) y 100.000 (sí aplica). Es un ejercicio propuesto, no una regla incluida.

**Respaldo reproducible.** Desde la raíz del proyecto, con las dependencias instaladas:

~~~sh
cd api
vendor/bin/phpunit ../fixtures/entrevista/EntrevistaTest.php
~~~

Usa SQLite en memoria. Comprueba lotes, totales, ajustes por paso, ticket inicial, filtro, conversión y atomicidad. La edición en vivo se demuestra en el navegador. El set pequeño explica decisiones de negocio; para sustentar escala usá el histórico de 10.000 y las mediciones del README raíz.
