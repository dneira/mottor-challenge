# Mottor · Comercial

Desde web/, con Node 22: `npm ci`, `npm test`, `npm run build` y `npm run dev`. Vite sirve en http://localhost:5173. Iniciar Laravel en otra terminal según el README raíz.

El cliente usa VITE_API_URL (por defecto http://127.0.0.1:8000). Usar un navegador moderno con Intl.Segmenter y BigInt. No hay claves ni servicios externos de interfaz.

El listado pide una página al servidor y espera 300 ms antes de aplicar el filtro de cliente. Las respuestas obsoletas no reemplazan resultados nuevos. Los montos y los IDs se leen sin pasar por float; sólo los conteos seguros se convierten a Number.

El importador lee el archivo local completo (hasta 100 MB), verifica versión v2, generated_at y count, y envía hasta 500 entradas por POST. El progreso cuenta únicamente lotes confirmados. Se puede detener entre lotes y continuar los pendientes en la misma sesión. Un rechazo 422 permite seguir con el siguiente lote; una respuesta incierta detiene el envío y exige revisar el histórico. No hay reintentos automáticos ni recuperación de la sesión tras recargar.

En el detalle se puede modificar la descripción de cada ítem para previsualizar sus renglones sin HTTP. Imprimir usa esas ediciones en un ticket completo y no persiste cambios. Las pruebas comparan el algoritmo con los mismos fixtures que PHP.
