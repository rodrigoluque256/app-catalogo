import { describe, expect, it } from 'vitest'
import { annualDiscountPercent, annualMonthlyEquivalent, annualSavings, limitLabel } from '@/lib/plans'
import type { Plan } from '@/lib/types'

const plan = (code: string, monthly: number): Plan => ({
  code, name: code, monthly_price: monthly, annual_price: (monthly * 72) / 10,
  limits: { products: 250, images_per_product: 5, users: 2 },
})

describe('plan prices', () => {
  it('computes the monthly equivalent and the yearly savings of the annual plan', () => {
    const basic = plan('basic', 1_000_000)

    expect(annualMonthlyEquivalent(basic)).toBe(600_000)
    expect(annualSavings(basic)).toBe(4_800_000)
  })

  it('derives the annual discount from the paid plans (and 0 if all are free)', () => {
    expect(annualDiscountPercent([plan('free', 0), plan('basic', 1_000_000)])).toBe(40)
    expect(annualDiscountPercent([plan('free', 0)])).toBe(0)
  })
})

describe('limitLabel', () => {
  it('handles singular, plural and unlimited', () => {
    expect(limitLabel(1, 'usuario', 'usuarios')).toBe('Hasta 1 usuario')
    expect(limitLabel(1000, 'producto', 'productos')).toBe('Hasta 1.000 productos')
    expect(limitLabel(null, 'producto', 'productos')).toBe('Productos sin límite')
  })
})
