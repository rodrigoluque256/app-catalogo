import Link from 'next/link'
import { buttonClass, PageHeader } from '@/components/admin/ui'
import { platformRequest, requirePlatformSession } from '@/lib/platform/api'
import type { Paginated } from '@/lib/types'
import type { PlatformStore, StoreListStatus } from '@/lib/platform/types'

async function storeCount(status: StoreListStatus): Promise<number> {
  const result = await platformRequest<Paginated<PlatformStore>>(`stores?per_page=1&status=${status}`, { envelope: true })

  return result.ok ? result.data.meta.total : 0
}

export default async function PlatformHome() {
  const session = await requirePlatformSession()
  const [active, suspended, deleted] = await Promise.all([storeCount('active'), storeCount('suspended'), storeCount('deleted')])

  const cards = [
    { label: 'Activas', value: active, href: '/platform/stores?status=active' },
    { label: 'Suspendidas', value: suspended, href: '/platform/stores?status=suspended' },
    { label: 'Borradas', value: deleted, href: '/platform/stores?status=deleted' },
  ]

  return (
    <>
      <PageHeader title={`Hola, ${session.admin.name}`}>
        <Link href="/platform/stores/new" className={buttonClass}>＋ Nueva tienda</Link>
      </PageHeader>
      <section aria-labelledby="tiendas" className="max-w-3xl">
        <h2 id="tiendas" className="mb-3 font-semibold">Tiendas</h2>
        <ul className="grid gap-3 sm:grid-cols-3">
          {cards.map((card) => (
            <li key={card.label}>
              <Link href={card.href} className="block rounded-lg border border-zinc-200 bg-white p-4 hover:border-zinc-400">
                <span className="block text-2xl font-semibold">{card.value}</span>
                <span className="text-sm text-zinc-600">{card.label}</span>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </>
  )
}
