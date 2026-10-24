import { describe, expect, it } from 'vitest'
import { buildProductPayload, centsToInput, emptyProductForm, newKey, parseMoneyToCents, productToForm } from '@/lib/admin/forms'
import type { AdminProduct } from '@/lib/admin/types'

describe('parseMoneyToCents', () => {
  it.each([
    ['18500', 1_850_000],
    ['18.500', 1_850_000],
    ['18.500,50', 1_850_050],
    ['18,500.50', 1_850_050],
    ['12.5', 1_250],
    ['12,5', 1_250],
    ['$ 1.234,05', 123_405],
    ['0', 0],
    ['0,99', 99],
  ])('parses %s', (input, expected) => expect(parseMoneyToCents(input)).toBe(expected))

  it.each([[''], ['abc'], ['12,345'], ['1,2,3x']])('rejects %j', (input) => {
    // "12,345" son 3 decimales: ambiguo/inválido (no se redondea en silencio)
    expect(parseMoneyToCents(input)).toBeNull()
  })

  it('does not lose cents to floating point', () => {
    expect(parseMoneyToCents('0.29')).toBe(29)
    expect(parseMoneyToCents('1.15')).toBe(115)
    expect(parseMoneyToCents('19.99')).toBe(1_999)
  })
})

describe('centsToInput', () => {
  it('round-trips with parseMoneyToCents', () => {
    for (const cents of [0, 5, 99, 100, 1_850_000, 1_850_050]) {
      expect(parseMoneyToCents(centsToInput(cents))).toBe(cents)
    }
    expect(centsToInput(null)).toBe('')
  })
})

describe('buildProductPayload', () => {
  const valid = () => ({ ...emptyProductForm(), name: ' Remera ', price: '18.500', status: 'active' as const })

  it('builds the API payload with cents, nulls for empty optionals and the first image as primary', () => {
    const form = {
      ...valid(),
      compareAt: '20000',
      images: [
        { key: 'a', path: 'stores/x/products/a.webp', url: 'u', altText: ' Frente ' },
        { key: 'b', path: 'stores/x/products/b.webp', url: 'u', altText: '' },
      ],
      optionNames: ['talle', 'color'],
      variants: [{ key: 'v', values: ['M', ' Negro '], sku: '', price: '19000', stock: '', isActive: true }],
    }

    const result = buildProductPayload(form)

    expect(result).toEqual({
      ok: true,
      payload: {
        name: 'Remera', slug: null, description: null, category_id: null, sku: null,
        price_amount: 1_850_000, compare_at_amount: 2_000_000, status: 'active', is_featured: false,
        variants: [{ options: { talle: 'M', color: 'Negro' }, sku: null, price_amount: 1_900_000, stock: null, is_active: true }],
        images: [
          { path: 'stores/x/products/a.webp', alt_text: 'Frente', is_primary: true },
          { path: 'stores/x/products/b.webp', alt_text: null, is_primary: false },
        ],
      },
    })
  })

  it('reports invalid prices, a compare-at price not above the price, and bad stock', () => {
    const result = buildProductPayload({
      ...valid(), price: 'gratis', compareAt: '10',
      optionNames: ['talle'], variants: [{ key: 'v', values: ['M'], sku: '', price: 'x', stock: '-1', isActive: true }],
    })

    expect(result.ok).toBe(false)
    if (!result.ok) {
      expect(Object.keys(result.errors).sort()).toEqual(['price_amount', 'variants.0.price_amount', 'variants.0.stock'])
    }
  })

  it('flags compare-at <= price, duplicate/empty option names and variants without options', () => {
    const a = buildProductPayload({ ...valid(), compareAt: '18500' })
    expect(!a.ok && a.errors.compare_at_amount).toBeTruthy()

    const b = buildProductPayload({ ...valid(), optionNames: ['talle', 'Talle'] })
    expect(!b.ok && b.errors.variants).toBeTruthy()

    const c = buildProductPayload({
      ...valid(), optionNames: ['talle'], variants: [{ key: 'v', values: [' '], sku: '', price: '', stock: '', isActive: true }],
    })
    expect(!c.ok && c.errors['variants.0.options']).toBeTruthy()
  })
})

describe('productToForm', () => {
  it('maps a product back to form state, primary image first, option columns from variants', () => {
    const product: AdminProduct = {
      id: 'p', name: 'Remera', slug: 'remera', description: null, sku: null, price_amount: 1_850_000,
      compare_at_amount: null, status: 'active', is_featured: true, category: null, updated_at: null,
      variants: [
        { id: 'v2', sku: 'B', options: { talle: 'L' }, price_amount: null, stock: 0, in_stock: false, is_active: true, position: 1 },
        { id: 'v1', sku: 'A', options: { talle: 'M', color: 'Negro' }, price_amount: 1_900_000, stock: null, in_stock: true, is_active: false, position: 0 },
      ],
      images: [
        { id: 'i1', url: 'u1', path: 'p1', alt_text: null, width: null, height: null, position: 0, is_primary: false },
        { id: 'i2', url: 'u2', path: 'p2', alt_text: 'x', width: null, height: null, position: 1, is_primary: true },
      ],
    }

    const form = productToForm(product)

    expect(form.price).toBe('18500')
    expect(form.optionNames).toEqual(['talle', 'color'])
    expect(form.variants.map((v) => v.values)).toEqual([['M', 'Negro'], ['L', '']])
    expect(form.variants[0]).toMatchObject({ price: '19000', stock: '', isActive: false })
    expect(form.variants[1]).toMatchObject({ stock: '0' })
    expect(form.images.map((i) => i.path)).toEqual(['p2', 'p1'])
  })
})

describe('newKey', () => {
  it('generates unique keys without crypto.randomUUID', () => {
    const original = globalThis.crypto
    Object.defineProperty(globalThis, 'crypto', { value: undefined, configurable: true })
    try {
      const keys = new Set(Array.from({ length: 1000 }, newKey))
      expect(keys.size).toBe(1000)
    } finally {
      Object.defineProperty(globalThis, 'crypto', { value: original, configurable: true })
    }
  })
})
