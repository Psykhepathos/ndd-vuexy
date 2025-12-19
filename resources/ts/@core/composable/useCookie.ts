// Ported from [Nuxt](https://github.com/nuxt/nuxt/blob/main/packages/nuxt/src/app/composables/cookie.ts)

import type { CookieParseOptions, CookieSerializeOptions } from 'cookie-es'
import { parse, serialize } from 'cookie-es'
import { destr } from 'destr'

type _CookieOptions = Omit<CookieSerializeOptions & CookieParseOptions, 'decode' | 'encode'>

export interface CookieOptions<T = any> extends _CookieOptions {
  decode?(value: string): T
  encode?(value: T): string
  default?: () => T | Ref<T>
  watch?: boolean | 'shallow'
}

export type CookieRef<T> = Ref<T>

/**
 * Detecta o path base da aplicação para cookies
 * Suporta deploy em subdiretório (ex: /valepedagio)
 *
 * IMPORTANTE: Cookies devem usar o path base da aplicação SEM /build
 * para garantir que sejam criados e deletados corretamente.
 *
 * Exemplos:
 * - Desenvolvimento: BASE_URL='/' -> path='/'
 * - Produção com ASSET_URL=/valepedagio: BASE_URL='/valepedagio/build' -> path='/valepedagio'
 */
const getBasePath = (): string => {
  const baseUrl = import.meta.env.BASE_URL || '/'

  // Remove '/build' do final se presente (Vite adiciona automaticamente)
  // Também remove trailing slash para consistência
  let path = baseUrl.replace(/\/build\/?$/, '').replace(/\/$/, '')

  // Garantir que sempre tenha pelo menos '/'
  return path || '/'
}

const CookieDefaults: CookieOptions<any> = {
  path: getBasePath(),
  watch: true,
  decode: val => destr(decodeURIComponent(val)),
  encode: val => encodeURIComponent(typeof val === 'string' ? val : JSON.stringify(val)),
}

export const useCookie = <T = string | null | undefined>(name: string, _opts?: CookieOptions<T>): CookieRef<T> => {
  const opts = { ...CookieDefaults, ..._opts || {} }
  const cookies = parse(document.cookie, opts)

  const cookie = ref<T | undefined>(cookies[name] as any ?? opts.default?.())

  watch(cookie, () => {
    document.cookie = serializeCookie(name, cookie.value, opts)
  })

  return cookie as CookieRef<T>
}

/**
 * Serializa cookie para document.cookie
 *
 * Ao deletar (value = null), tenta limpar em múltiplos paths
 * para garantir remoção mesmo que o cookie tenha sido criado
 * com path diferente (ex: migração de / para /valepedagio)
 */
function serializeCookie(name: string, value: any, opts: CookieSerializeOptions = {}) {
  if (value === null || value === undefined) {
    const basePath = opts.path || '/'
    const pathsToTry = new Set([basePath])

    // Adicionar paths alternativos comuns para garantir limpeza
    pathsToTry.add('/')
    if (basePath !== '/') {
      pathsToTry.add(basePath + '/build') // Vite pode ter criado com /build
    }

    // Gerar múltiplos Set-Cookie para limpar em todos os paths
    return Array.from(pathsToTry).map(path =>
      serialize(name, '', { ...opts, path, maxAge: -1 })
    ).join('; ')
  }

  return serialize(name, value, { ...opts, maxAge: 60 * 60 * 24 * 30 })
}
