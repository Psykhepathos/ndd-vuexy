/**
 * Global type definitions for Vuetify
 * Adds $vuetify property to Vue component instances
 */

import type { Framework } from 'vuetify'

declare module 'vue' {
  interface ComponentCustomProperties {
    $vuetify: Framework
  }
}

export {}
