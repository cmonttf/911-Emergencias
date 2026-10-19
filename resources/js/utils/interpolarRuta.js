/**
 * Calcula la distancia aproximada en metros entre dos coordenadas
 * [longitud, latitud], usando la fórmula de Haversine. Replica
 * App\Dominio\Geo\ValueObjects\PuntoGeografico::distanciaMetrosHacia en PHP.
 */
function distanciaMetros([lngA, latA], [lngB, latB]) {
    const radioTierraMetros = 6_371_000;
    const aRad = (grados) => (grados * Math.PI) / 180;

    const anguloCoseno = Math.min(
        1,
        Math.max(
            -1,
            Math.sin(aRad(latA)) * Math.sin(aRad(latB)) +
                Math.cos(aRad(latA)) * Math.cos(aRad(latB)) * Math.cos(aRad(lngB - lngA)),
        ),
    );

    return radioTierraMetros * Math.acos(anguloCoseno);
}

/**
 * Camino que le falta recorrer a un vehículo a partir de la fracción `f` de
 * su ruta: el punto exacto donde va ahora (mismo cálculo que
 * App\Dominio\Geo\InterpoladorRuta::puntoEnFraccion en PHP, que el servidor
 * usa para recalcular rutas de regreso), seguido de los vértices originales
 * que todavía no pasó. El primer elemento sirve para ubicar el marcador del
 * vehículo; el arreglo completo, para dibujar la ruta en el mapa "borrando"
 * el tramo ya recorrido en vez de mostrar la ruta completa fija (RNF-02: se
 * calcula en el cliente a partir de la geometría y la hora, nunca se le pide
 * la posición al servidor).
 *
 * @param {Array<[number, number]>} geometria
 * @param {number} fraccion Entre 0 y 1.
 * @returns {Array<[number, number]>}
 */
export function caminoRestante(geometria, fraccion) {
    const f = Math.min(1, Math.max(0, fraccion));

    if (!geometria || geometria.length < 2) {
        return geometria ?? [];
    }

    const distanciasPorTramo = [];
    let distanciaTotal = 0;

    for (let i = 0; i < geometria.length - 1; i++) {
        const distancia = distanciaMetros(geometria[i], geometria[i + 1]);
        distanciasPorTramo.push(distancia);
        distanciaTotal += distancia;
    }

    if (distanciaTotal === 0) {
        return geometria;
    }

    const distanciaObjetivo = f * distanciaTotal;
    let distanciaAcumulada = 0;

    for (let i = 0; i < distanciasPorTramo.length; i++) {
        const distanciaTramo = distanciasPorTramo[i];

        if (distanciaAcumulada + distanciaTramo >= distanciaObjetivo) {
            const avance = distanciaTramo > 0 ? (distanciaObjetivo - distanciaAcumulada) / distanciaTramo : 0;
            const [lngInicio, latInicio] = geometria[i];
            const [lngFin, latFin] = geometria[i + 1];
            const puntoActual = [
                lngInicio + avance * (lngFin - lngInicio),
                latInicio + avance * (latFin - latInicio),
            ];

            return [puntoActual, ...geometria.slice(i + 1)];
        }

        distanciaAcumulada += distanciaTramo;
    }

    return [geometria[geometria.length - 1]];
}

/**
 * Fracción recorrida de un tramo según la hora actual (RNF-02):
 * `f = (ahora - salida) / (llegada - salida)`.
 */
export function fraccionDeTiempo(salidaEn, llegadaEn) {
    const salida = new Date(salidaEn).getTime();
    const llegada = new Date(llegadaEn).getTime();
    const ahora = Date.now();

    if (llegada <= salida) {
        return 1;
    }

    return Math.min(1, Math.max(0, (ahora - salida) / (llegada - salida)));
}
