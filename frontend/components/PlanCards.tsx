'use client'

import { useState } from 'react'
import { formatMoney } from '@/lib/money'
import { annualDiscountPercent, annualMonthlyEquivalent, annualSavings, limitLabel, type BillingCycle } from '@/lib/plans'
import type { Plan } from '@/lib/types'

const money = (cents: number) => formatMoney(cents, 'ARS', 'es-AR')

type Props = {
  plans: Plan[]
  /** Plan vigente de la tienda (se marca y no muestra acción). */
  currentCode?: string
  /** Acción de cada tarjeta (landing: "Empezar"; registro: "Elegir"). Sin ella, solo informa. */
  renderAction?: (plan: Plan, cycle: BillingCycle) => React.ReactNode
}

/** Tarjetas de planes con selector mensual/anual. Se reutiliza en "Mi plan", la landing y el registro. */
export function PlanCards({ plans, currentCode, renderAction }: Props) {
  const [cycle, setCycle] = useState<BillingCycle>('monthly')
  const discount = annualDiscountPercent(plans)

  return (
    <div className="flex flex-col gap-4">
      <div role="radiogroup" aria-label="Forma de pago" className="inline-flex self-start rounded-lg border border-zinc-300 bg-white p-1 text-sm">
        {(['monthly', 'annual'] as const).map((option) => (
          <button
            key={option}
            type="button"
            role="radio"
            aria-checked={cycle === option}
            onClick={() => setCycle(option)}
            className={`rounded-md px-3 py-1.5 ${cycle === option ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-100'}`}
          >
            {option === 'monthly' ? 'Mensual' : 'Anual'}
            {option === 'annual' && discount > 0 && <span className="ml-1 font-semibold">−{discount}%</span>}
          </button>
        ))}
      </div>

      <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        {plans.map((plan) => {
          const current = plan.code === currentCode
          const free = plan.monthly_price === 0

          return (
            <li key={plan.code} className={`flex flex-col gap-3 rounded-lg border bg-white p-4 ${current ? 'border-zinc-900 ring-1 ring-zinc-900' : 'border-zinc-200'}`}>
              <div className="flex items-center justify-between gap-2">
                <h3 className="font-semibold">{plan.name}</h3>
                {current && <span className="rounded-full bg-zinc-900 px-2 py-0.5 text-xs text-white">Tu plan</span>}
              </div>

              {free ? (
                <p><span className="text-2xl font-semibold">{money(0)}</span></p>
              ) : cycle === 'monthly' ? (
                <p><span className="text-2xl font-semibold">{money(plan.monthly_price)}</span> <span className="text-sm text-zinc-600">por mes</span></p>
              ) : (
                <div>
                  <p><span className="text-2xl font-semibold">{money(annualMonthlyEquivalent(plan))}</span> <span className="text-sm text-zinc-600">por mes</span></p>
                  <p className="text-xs text-zinc-600">{money(plan.annual_price)} por año · ahorrás {money(annualSavings(plan))}</p>
                </div>
              )}

              <ul className="flex flex-col gap-1 text-sm text-zinc-700">
                <li>{limitLabel(plan.limits.products, 'producto', 'productos')}</li>
                <li>{limitLabel(plan.limits.images_per_product, 'foto', 'fotos')} por producto</li>
                <li>{limitLabel(plan.limits.users, 'usuario', 'usuarios')}</li>
              </ul>

              {!current && renderAction && <div className="mt-auto">{renderAction(plan, cycle)}</div>}
            </li>
          )
        })}
      </ul>
      <p className="text-xs text-zinc-600">Precios en pesos argentinos, finales.</p>
    </div>
  )
}
