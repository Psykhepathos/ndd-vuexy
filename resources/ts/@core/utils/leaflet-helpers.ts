/**
 * Leaflet Type Helpers
 *
 * Resolve problemas de tipagem conhecidos do @types/leaflet
 * onde o tipo Map é incompatível com Map | LayerGroup
 */

import L from 'leaflet'

/**
 * Type-safe wrapper para adicionar layer ao mapa
 * Resolve: "Argument of type 'Map' is not assignable to parameter of type 'Map | LayerGroup'"
 */
export function addLayerToMap(layer: L.Layer, map: L.Map): void {
  layer.addTo(map as any)
}

/**
 * Type-safe wrapper para remover layer do mapa
 */
export function removeLayerFromMap(layer: L.Layer): void {
  layer.remove()
}

/**
 * Type-safe wrapper para criar polyline
 * Resolve problemas com LatLngExpression[]
 */
export function createPolyline(
  latlngs: Array<[number, number]> | L.LatLng[],
  options?: L.PolylineOptions
): L.Polyline {
  return L.polyline(latlngs as any, options)
}

/**
 * Type-safe wrapper para fitBounds
 */
export function fitMapBounds(map: L.Map, bounds: L.LatLngBounds, options?: L.FitBoundsOptions): void {
  map.fitBounds(bounds, options)
}
