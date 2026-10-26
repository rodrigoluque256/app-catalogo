'use client'

import { useState, useTransition } from 'react'
import { changeStorePlanAction } from '@/app/platform/actions'
import { buttonClass, Field, inputClass } from '@/components/admin/ui'
import type { PlatformStore } from '@/lib/platform/types'
import type { Plan } from '@/lib/types'

/** Cambio manual de plan (ej. pagó por transferencia) hasta que lo haga Mercado Pago. */
export function ChangePlanForm({ store, plans }: { store: PlatformStore; plans: Plan[] }) {
  const [plan, setPlan] = useState(store.plan.code)
  const [cycle, setCycle] = useState<'monthly' | 'annual'>(store.plan.billing_cycle ?? 'monthly')
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null)
  const [pending, startTransition] = useTransition()
  const paid = (plans.find((p) => p.code === plan)?.monthly_price ?? 0) > 0
  const unchanged = plan === store.plan.code && (!paid || cycle === store.plan.billing_cycle)

  function submit(event: React.FormEvent) {
    event.preventDefault()
    setMessage(null)

    startTransition(async () => {
      const result = await changeStorePlanAction(store.id, plan, paid ? cycle : null)
      setMessage(result.ok ? { ok: true, text: 'Plan actualizado.' } : { ok: false, text: result.errors.plan?.[0] ?? result.errors.billing_cycle?.[0] ?? result.message })
    })
  }

  return (
    <form onSubmit={submit} aria-labelledby="plan" className="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-5">
      <h2 id="plan" className="font-semibold">Plan</h2>
      <p className="text-sm text-zinc-600">
        Bajar de plan no borra nada: la tienda conserva lo que tiene y solo no puede agregar más de lo que permite el plan nuevo.
      </p>
      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Plan" htmlFor="plan-code">
          <select id="plan-code" className={inputClass} value={plan} onChange={(e) => setPlan(e.target.value)}>
            {plans.map((option) => <option key={option.code} value={option.code}>{option.name}</option>)}
          </select>
        </Field>
        {paid && (
          <Field label="Ciclo de cobro" htmlFor="plan-cycle">
            <select id="plan-cycle" className={inputClass} value={cycle} onChange={(e) => setCycle(e.target.value as 'monthly' | 'annual')}>
              <option value="monthly">Mensual</option>
              <option value="annual">Anual</option>
            </select>
          </Field>
        )}
      </div>
      {message && <p role={message.ok ? 'status' : 'alert'} className={`text-sm ${message.ok ? 'text-green-700' : 'text-red-600'}`}>{message.text}</p>}
      <div>
        <button type="submit" disabled={pending || unchanged} className={buttonClass}>{pending ? 'Guardando…' : 'Cambiar plan'}</button>
      </div>
    </form>
  )
}
