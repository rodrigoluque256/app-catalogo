import { describe, expect, it } from 'vitest'
import { readableForeground } from '@/lib/color'
import { storeSlugFromHost } from '@/lib/host'
import { formatMoney } from '@/lib/money'
import { findVariant, isSoldOut, listingPrice, optionGroups, primaryImage } from '@/lib/products'
import { tags } from '@/lib/tags'
import type { Product, ProductVariant } from '@/lib/types'
import { buildWhatsAppMessage, buildWhatsAppUrl } from '@/lib/whatsapp'

const variant = (id: string, options: Record<string, string>, extra: Partial<ProductVariant> = {}): ProductVariant => ({
  id, options, price_amount: null, in_stock: true, position: 0, ...extra,
})

const product = (extra: Partial<Product> = {}): Product => ({
  id: 'p1', name: 'Remera', slug: 'remera', description: null, price_amount: 1_000_00, compare_at_amount: null,
  is_featured: false, category: null, variants: [], images: [], ...extra,
})

describe('storeSlugFromHost', () => {
  it('extracts the subdomain', () => {
    expect(storeSlugFromHost('demo.localhost:3000', 'localhost')).toBe('demo')
    expect(storeSlugFromHost('Mi-Tienda.catalogo.com', 'catalogo.com')).toBe('mi-tienda')
  })

  it('ignores the root domain, www, other domains and nested subdomains', () => {
    expect(storeSlugFromHost('localhost:3000', 'localhost')).toBeNull()
    expect(storeSlugFromHost('www.catalogo.com', 'catalogo.com')).toBeNull()
    expect(storeSlugFromHost('demo.otro.com', 'catalogo.com')).toBeNull()
    expect(storeSlugFromHost('a.b.catalogo.com', 'catalogo.com')).toBeNull()
    expect(storeSlugFromHost('evilcatalogo.com', 'catalogo.com')).toBeNull()
    expect(storeSlugFromHost(null, 'catalogo.com')).toBeNull()
  })
})

describe('tags (contrato con el backend)', () => {
  it('matches the backend StorefrontTags convention', () => {
    expect(tags.store('demo')).toBe('store:demo')
    expect(tags.products('demo')).toBe('store:demo:products')
    expect(tags.categories('demo')).toBe('store:demo:categories')
    expect(tags.product('demo', 'remera')).toBe('store:demo:product:remera')
  })
})

describe('formatMoney', () => {
  it('formats cents and hides empty decimals', () => {
    expect(formatMoney(1_850_000, 'ARS', 'es-AR')).toMatch(/18\.500/)
    expect(formatMoney(1_850_050, 'ARS', 'es-AR')).toMatch(/18\.500,50/)
  })
})

describe('whatsapp', () => {
  it('fills the template with the product and chosen options', () => {
    const message = buildWhatsAppMessage({
      template: 'Hola! Quiero {product}',
      productName: 'Remera',
      options: { talle: 'M', color: 'Negro' },
      productUrl: 'https://demo.x/p/remera',
    })
    expect(message).toBe('Hola! Quiero Remera (talle: M, color: Negro)\nhttps://demo.x/p/remera')
  })

  it('falls back to the default template', () => {
    expect(buildWhatsAppMessage({ template: null, productName: 'Remera' })).toBe('Hola! Me interesa Remera.')
  })

  it('builds a wa.me link with digits only and an encoded text', () => {
    expect(buildWhatsAppUrl('+54 9 11 2233-4455', 'Hola & chau')).toBe('https://wa.me/5491122334455?text=Hola%20%26%20chau')
    expect(buildWhatsAppUrl(null, 'x')).toBeNull()
  })
})

describe('products', () => {
  const variants = [
    variant('a', { talle: 'S', color: 'Negro' }, { position: 0 }),
    variant('b', { talle: 'M', color: 'Negro' }, { position: 1, price_amount: 1_200_00 }),
    variant('c', { talle: 'M', color: 'Blanco' }, { position: 2, in_stock: false }),
  ]

  it('groups options in order of appearance', () => {
    expect(optionGroups(variants)).toEqual({ talle: ['S', 'M'], color: ['Negro', 'Blanco'] })
  })

  it('finds the variant for a full selection only', () => {
    expect(findVariant(variants, { talle: 'M', color: 'Negro' })?.id).toBe('b')
    expect(findVariant(variants, { talle: 'S', color: 'Blanco' })).toBeNull()
    expect(findVariant(variants, { talle: 'M' })).toBeNull()
  })

  it('shows "from" pricing only when variants differ', () => {
    expect(listingPrice(product({ variants }))).toEqual({ amount: 1_000_00, isFrom: true })
    expect(listingPrice(product({ variants: [variant('a', {})] }))).toEqual({ amount: 1_000_00, isFrom: false })
  })

  it('is sold out only when every variant is out of stock', () => {
    expect(isSoldOut(product({ variants }))).toBe(false)
    expect(isSoldOut(product({ variants: [variant('x', {}, { in_stock: false })] }))).toBe(true)
    expect(isSoldOut(product())).toBe(false)
  })

  it('prefers the primary image, otherwise the first by position', () => {
    const image = (id: string, position: number, is_primary = false) => ({
      id, url: `u/${id}`, alt_text: null, width: null, height: null, position, is_primary, variant_id: null,
    })
    expect(primaryImage(product({ images: [image('a', 1), image('b', 2, true)] }))?.id).toBe('b')
    expect(primaryImage(product({ images: [image('a', 2), image('b', 1)] }))?.id).toBe('b')
    expect(primaryImage(product())).toBeNull()
  })
})

describe('readableForeground', () => {
  it('picks a contrasting text color', () => {
    expect(readableForeground('#18181B')).toBe('#ffffff')
    expect(readableForeground('#FFFFFF')).toBe('#18181b')
    expect(readableForeground('#FACC15')).toBe('#18181b')
    expect(readableForeground('invalido')).toBe('#ffffff')
  })
})
