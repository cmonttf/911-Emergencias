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
 * Punto [longitud, latitud] a la fracción `f` del recorrido de una ruta.
 * Replica App\Dominio\Geo\InterpoladorRuta::puntoEnFraccion en PHP: la misma
 * fórmula corre en el servidor (para recalcular rutas de regreso) y en el
 * cliente (para dibujar el vehículo sin que el servidor transmita
 * posiciones intermedias, RNF-02).
 *
 * @param {Array<[number, number]>} geometria
 * @param {number} fraccion Entre 0 y 1.
 * @returns {[number, number]}
 */
export function puntoEnFraccion(geometria, fraccion) {
    const f = Math.min(1, Math.max(0, fraccion));

    if (!geometria || geometria.length < 2) {
        return geometria?.[0] ?? [0, 0];
    }

    const distanciasPorTramo = [];
    let distanciaTotal = 0;

    for (let i = 0; i < geometria.length - 1; i++) {
        const distancia = distanciaMetros(geometria[i], geometria[i + 1]);
        distanciasPorTramo.push(distancia);
        distanciaTotal += distancia;
    }

    if (distanciaTotal === 0) {
        return geometria[0];
    }

    const distanciaObjetivo = f * distanciaTotal;
    let distanciaAcumulada = 0;

    for (let i = 0; i < distanciasPorTramo.length; i++) {
        const distanciaTramo = distanciasPorTramo[i];

        if (distanciaAcumulada + distanciaTramo >= distanciaObjetivo) {
            const avance = distanciaTramo > 0 ? (distanciaObjetivo - distanciaAcumulada) / distanciaTramo : 0;
            const [lngInicio, latInicio] = geometria[i];
            const [lngFin, latFin] = geometria[i + 1];

            return [lngInicio + avance * (lngFin - lngInicio), latInicio + avance * (latFin - latInicio)];
        }

        distanciaAcumulada += distanciaTramo;
    }

    return geometria[geometria.length - 1];
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
