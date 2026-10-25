import { afterEach, describe, expect, it, vi } from 'vitest'
import { excerpt, ogLocale, storeOpenGraph, storeOrigin, storeUrl } from '@/lib/seo'
import type { Store } from '@/lib/types'

const store = (extra: Partial<Store> = {}): Store => ({
  id: 's1', name: 'Demo', slug: 'demo', custom_domain: null, logo_url: null,
  theme: { primary_color: '#000000', accent_color: '#2563eb' }, whatsapp: { number: null, message_template: null },
  currency: 'ARS', locale: 'es-AR', ...extra,
})

afterEach(() => vi.unstubAllEnvs())

describe('storeOrigin / storeUrl', () => {
  it('defaults to https on the store subdomain', () => {
    vi.stubEnv('STORE_ORIGIN_TEMPLATE', '')
    vi.stubEnv('ROOT_DOMAIN', 'catalogo.com')
    expect(storeOrigin('demo')).toBe('https://demo.catalogo.com')
    expect(storeUrl('demo')).toBe('https://demo.catalogo.com/')
  })

  it('uses the template (scheme and port) and joins paths', () => {
    vi.stubEnv('STORE_ORIGIN_TEMPLATE', 'http://{store}.localhost:3000/')
    expect(storeOrigin('demo')).toBe('http://demo.localhost:3000')
    expect(storeUrl('demo', '/p/remera')).toBe('http://demo.localhost:3000/p/remera')
    expect(storeUrl('demo', 'sitemap.xml')).toBe('http://demo.localhost:3000/sitemap.xml')
  })
})

describe('storeOpenGraph', () => {
  it('uses og:locale format and the logo when present', () => {
    expect(ogLocale('es-AR')).toBe('es_AR')
    expect(storeOpenGraph(store())).toMatchObject({ siteName: 'Demo', locale: 'es_AR', images: undefined })
    expect(storeOpenGraph(store({ logo_url: 'https://cdn.x/logo.png' })).images).toEqual([
      { url: 'https://cdn.x/logo.png', alt: 'Demo' },
    ])
  })
})

describe('excerpt', () => {
  it('collapses whitespace and handles empty input', () => {
    expect(excerpt('  Remera\n\nde  algodón ')).toBe('Remera de algodón')
    expect(excerpt(null)).toBeUndefined()
    expect(excerpt('   ')).toBeUndefined()
  })

  it('cuts on a word boundary within the limit', () => {
    const result = excerpt('palabra '.repeat(40), 50) ?? ''
    expect(result.length).toBeLessThanOrEqual(50)
    expect(result).toMatch(/palabra…$/)
  })
})
