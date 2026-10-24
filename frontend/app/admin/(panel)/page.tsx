import Link from 'next/link'
import { PageHeader } from '@/components/admin/ui'
import { adminRequest, can, requireSession, storefrontUrl } from '@/lib/admin/api'
import type { AdminProduct, Category, Paginated } from '@/lib/admin/types'

async function productCount(status: string): Promise<number> {
  const result = await adminRequest<Paginated<AdminProduct>>(`admin/products?per_page=1&status=${status}`, { envelope: true })

  return result.ok ? result.data.meta.total : 0
}

export default async function Dashboard() {
  const session = await requireSession()
  const canProducts = can(session, 'products.manage')

  const [active, draft, categories] = canProducts
    ? await Promise.all([
        productCount('active'),
        productCount('draft'),
        adminRequest<Category[]>('admin/categories').then((r) => (r.ok ? r.data.length : 0)),
      ])
    : [0, 0, 0]

  const checklist = [
    { done: session.store.logo_url !== null, text: 'Subí el logo de tu tienda', href: '/admin/settings', show: can(session, 'store.manage') },
    { done: session.store.whatsapp.number !== null, text: 'Configurá tu WhatsApp para recibir consultas', href: '/admin/settings', show: can(session, 'store.manage') },
    { done: categories > 0, text: 'Creá tu primera categoría', href: '/admin/categories/new', show: canProducts },
    { done: active > 0, text: 'Publicá tu primer producto (estado Activo)', href: '/admin/products/new', show: canProducts },
  ].filter((item) => item.show)

  const pending = checklist.filter((item) => !item.done)

  return (
    <>
      <PageHeader title={`Hola, ${session.user.name}`}>
        <a href={storefrontUrl(session.store.slug)} target="_blank" rel="noreferrer" className="text-sm underline">
          Abrir mi tienda ↗
        </a>
      </PageHeader>

      {canProducts && (
        <div className="mb-8 grid gap-4 sm:grid-cols-3">
          {[
            { label: 'Productos activos', value: active },
            { label: 'Borradores', value: draft },
            { label: 'Categorías', value: categories },
          ].map((stat) => (
            <div key={stat.label} className="rounded-lg border border-zinc-200 bg-white p-4">
              <p className="text-3xl font-semibold">{stat.value}</p>
              <p className="text-sm text-zinc-500">{stat.label}</p>
            </div>
          ))}
        </div>
      )}

      {pending.length > 0 && (
        <section className="max-w-xl rounded-lg border border-zinc-200 bg-white p-5" aria-labelledby="primeros-pasos">
          <h2 id="primeros-pasos" className="mb-3 font-semibold">Primeros pasos</h2>
          <ul className="flex flex-col gap-2 text-sm">
            {checklist.map((item) => (
              <li key={item.text} className="flex items-center gap-2">
                <span aria-hidden>{item.done ? '✅' : '⬜'}</span>
                {item.done ? <span className="text-zinc-400 line-through">{item.text}</span> : <Link href={item.href} className="underline">{item.text}</Link>}
              </li>
            ))}
          </ul>
        </section>
      )}
    </>
  )
}
