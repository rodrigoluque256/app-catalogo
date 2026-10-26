import { describe, expect, it } from 'vitest'
import { slugify } from '@/lib/slug'

describe('slugify', () => {
  it('removes accents, symbols and repeated separators', () => {
    expect(slugify('Almacén  Don José & Hijos!')).toBe('almacen-don-jose-hijos')
    expect(slugify('  --Ñandú 2026--  ')).toBe('nandu-2026')
  })

  it('returns an empty string when nothing usable is left', () => {
    expect(slugify('¡¿?!')).toBe('')
  })

  it('cuts to 63 characters without leaving a trailing dash', () => {
    const slug = slugify(`${'a'.repeat(62)} b`)

    expect(slug).toBe('a'.repeat(62))
    expect(slugify('x'.repeat(100))).toHaveLength(63)
  })
})
