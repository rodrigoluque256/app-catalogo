import type { Metadata } from 'next'
import type { Store } from './types'

type OpenGraph = NonNullable<Metadata['openGraph']>

/**
 * Origen público de la tienda, para canonical, Open Graph y sitemap (que exigen URLs absolutas).
 * STORE_ORIGIN_TEMPLATE permite fijar esquema y puerto (ej. "http://{store}.localhost:3000" en desarrollo).
 * Los dominios propios todavía no se enrutan (ver lib/host.ts), por eso siempre se usa el subdominio.
 */
export function storeOrigin(slug: string): string {
  const template = process.env.STORE_ORIGIN_TEMPLATE || `https://{store}.${process.env.ROOT_DOMAIN ?? 'localhost'}`

  return template.replace('{store}', slug).replace(/\/+$/, '')
}

export function storeUrl(slug: string, path = '/'): string {
  return `${storeOrigin(slug)}${path.startsWith('/') ? path : `/${path}`}`
}

/** "es-AR" -> "es_AR", el formato que pide og:locale. */
export function ogLocale(locale: string): string {
  return locale.replace('-', '_')
}

/** Base de Open Graph de la tienda. Next no mezcla openGraph entre niveles: cada página parte de esta. */
export function storeOpenGraph(store: Store): OpenGraph {
  return {
    siteName: store.name,
    type: 'website',
    locale: ogLocale(store.locale),
    images: store.logo_url ? [{ url: store.logo_url, alt: store.name }] : undefined,
  }
}

/** Texto en una línea, cortado en un límite de palabra, para meta description. */
export function excerpt(text: string | null | undefined, max = 160): string | undefined {
  const clean = text?.replace(/\s+/g, ' ').trim()

  if (!clean) return undefined
  if (clean.length <= max) return clean

  const cut = clean.slice(0, max - 1)
  const space = cut.lastIndexOf(' ')

  return `${(space > max / 2 ? cut.slice(0, space) : cut).trimEnd()}…`
}
