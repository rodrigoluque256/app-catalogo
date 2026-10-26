import type { Plan } from './types'

export type BillingCycle = 'monthly' | 'annual'

/** Lo que paga por mes con el plan anual (el total se cobra por adelantado). En centavos. */
export function annualMonthlyEquivalent(plan: Plan): number {
  return Math.round(plan.annual_price / 12)
}

/** Cuánto ahorra al año pagando anual en vez de 12 meses. En centavos. */
export function annualSavings(plan: Plan): number {
  return plan.monthly_price * 12 - plan.annual_price
}

/** Descuento del anual en porcentaje entero (40 para los planes actuales); 0 en el plan gratis. */
export function annualDiscountPercent(plans: Plan[]): number {
  const paid = plans.find((plan) => plan.monthly_price > 0)

  return paid ? Math.round((annualSavings(paid) / (paid.monthly_price * 12)) * 100) : 0
}

export function limitLabel(value: number | null, singular: string, plural: string): string {
  if (value === null) return `${plural.charAt(0).toUpperCase()}${plural.slice(1)} sin límite`

  return `Hasta ${value.toLocaleString('es-AR')} ${value === 1 ? singular : plural}`
}
