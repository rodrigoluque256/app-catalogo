import type { Metadata } from 'next'
import { PageHeader } from '@/components/admin/ui'
import { PlanCards } from '@/components/PlanCards'
import { adminRequest, can, requireSession } from '@/lib/admin/api'
import type { StorePlan } from '@/lib/admin/types'
import { getPlans } from '@/lib/api'
import { limitLabel } from '@/lib/plans'

export const metadata: Metadata = { title: 'Mi plan' }

function Usage({ label, used, max }: { label: string; used: number; max: number | null }) {
  const percent = max === null ? 0 : Math.min(100, Math.round((used / max) * 100))
  const full = max !== null && used >= max

  return (
    <div className="flex flex-col gap-1">
      <div className="flex justify-between text-sm">
        <span>{label}</span>
        <span className={full ? 'font-medium text-red-700' : 'text-zinc-600'}>
          {used.toLocaleString('es-AR')} de {max === null ? 'sin límite' : max.toLocaleString('es-AR')}
        </span>
      </div>
      {max !== null && (
        <div
          role="progressbar" aria-label={label} aria-valuemin={0} aria-valuemax={max} aria-valuenow={Math.min(used, max)}
          className="h-2 overflow-hidden rounded-full bg-zinc-200"
        >
          <div className={`h-full ${full ? 'bg-red-600' : 'bg-zinc-900'}`} style={{ width: `${percent}%` }} />
        </div>
      )}
    </div>
  )
}

export default async function PlanPage() {
  const session = await requireSession()

  if (!can(session, 'store.manage')) {
    return <p className="rounded-lg border border-zinc-200 bg-white p-6">Solo el dueño de la tienda puede ver el plan.</p>
  }

  const [result, plans] = await Promise.all([adminRequest<StorePlan>('admin/plan'), getPlans()])

  if (!result.ok) return <p role="alert" className="text-red-600">{result.message}</p>

  const { plan, subscription, usage } = result.data
  const cycle = subscription.billing_cycle === 'annual' ? 'anual' : subscription.billing_cycle === 'monthly' ? 'mensual' : null

  return (
    <>
      <PageHeader title="Mi plan" />

      <section aria-labelledby="plan-actual" className="mb-8 flex max-w-2xl flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-5">
        <div>
          <h2 id="plan-actual" className="text-lg font-semibold">Plan {plan.name}{cycle && <span className="font-normal text-zinc-600"> · {cycle}</span>}</h2>
          <p className="text-sm text-zinc-600">{limitLabel(plan.limits.images_per_product, 'foto', 'fotos')} por producto.</p>
        </div>
        <Usage label="Productos" used={usage.products} max={plan.limits.products} />
        <Usage label="Usuarios" used={usage.users} max={plan.limits.users} />
        <p className="text-xs text-zinc-600">Si bajás de plan no se borra nada: solo no vas a poder agregar más hasta volver a estar dentro del límite.</p>
      </section>

      <section aria-labelledby="planes">
        <h2 id="planes" className="mb-1 text-lg font-semibold">Planes</h2>
        <p className="mb-4 text-sm text-zinc-600">
          Muy pronto vas a poder cambiar de plan y pagar con Mercado Pago desde acá. Mientras tanto, pedíselo al equipo de la plataforma.
        </p>
        <PlanCards plans={plans} currentCode={plan.code} />
      </section>
    </>
  )
}
