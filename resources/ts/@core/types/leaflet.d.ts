/**
 * Leaflet Type Extensions
 *
 * Estende os tipos do Leaflet para corrigir incompatibilidades conhecidas
 * entre Map e Map | LayerGroup nos métodos addTo() e remove()
 */

import 'leaflet'

declare module 'leaflet' {
  interface Layer {
    /**
     * Sobrescreve addTo para aceitar Map diretamente
     * Resolve: "Argument of type 'Map' is not assignable to parameter of type 'Map | LayerGroup'"
     */
    addTo(map: Map | LayerGroup<any>): this
  }

  interface Marker {
    addTo(map: Map | LayerGroup<any>): this
  }

  interface Polyline {
    addTo(map: Map | LayerGroup<any>): this
  }

  interface Polygon {
    addTo(map: Map | LayerGroup<any>): this
  }

  interface Circle {
    addTo(map: Map | LayerGroup<any>): this
  }

  interface CircleMarker {
    addTo(map: Map | LayerGroup<any>): this
  }

  interface TileLayer {
    addTo(map: Map | LayerGroup<any>): this
  }

  interface FeatureGroup {
    addTo(map: Map | LayerGroup<any>): this
  }

  interface GeoJSON {
    addTo(map: Map | LayerGroup<any>): this
  }
}
