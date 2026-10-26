import type { Metadata } from 'next'
import Link from 'next/link'
import { buttonClass, inputClass, PageHeader, secondaryButtonClass } from '@/components/admin/ui'
import { StoreStatusBadge } from '@/components/platform/StoreStatusBadge'
import { storefrontUrl } from '@/lib/admin/api'
import { platformRequest } from '@/lib/platform/api'
import type { Paginated } from '@/lib/types'
import type { PlatformStore } from '@/lib/platform/types'

export const metadata: Metadata = { title: 'Tiendas' }

type Props = { searchParams: Promise<{ search?: string; status?: string; page?: string }> }

const tabs = [
  { status: '', label: 'Activas y suspendidas' },
  { status: 'active', label: 'Activas' },
  { status: 'suspended', label: 'Suspendidas' },
  { status: 'deleted', label: 'Borradas' },
]

export default async function StoresPage({ searchParams }: Props) {
  const { search = '', status: rawStatus = '', page = '1' } = await searchParams
  const status = tabs.some((tab) => tab.status === rawStatus) ? rawStatus : ''

  const query = new URLSearchParams({ per_page: '25', page: String(Math.max(1, Number(page) || 1)) })
  if (search) query.set('search', search)
  if (status) query.set('status', status)

  const result = await platformRequest<Paginated<PlatformStore>>(`stores?${query}`, { envelope: true })

  if (!result.ok) return <p role="alert" className="text-red-600">{result.message}</p>

  const { data: stores, meta } = result.data
  const link = (params: { status?: string; page?: number }) => {
    const next = new URLSearchParams()
    const nextStatus = params.status ?? status
    if (search) next.set('search', search)
    if (nextStatus) next.set('status', nextStatus)
    if (params.page && params.page > 1) next.set('page', String(params.page))
    const qs = next.toString()

    return `/platform/stores${qs ? `?${qs}` : ''}`
  }

  return (
    <>
      <PageHeader title="Tiendas">
        <Link href="/platform/stores/new" className={buttonClass}>＋ Nueva tienda</Link>
      </PageHeader>

      <nav aria-label="Filtrar por estado" className="mb-4 flex flex-wrap gap-1">
        {tabs.map((tab) => (
          <Link
            key={tab.status || 'all'}
            href={link({ status: tab.status, page: 1 })}
            aria-current={tab.status === status ? 'page' : undefined}
            className={`rounded-md px-3 py-1.5 text-sm ${tab.status === status ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-200'}`}
          >
            {tab.label}
          </Link>
        ))}
      </nav>

      <form className="mb-4 flex flex-wrap gap-2" role="search">
        {status && <input type="hidden" name="status" value={status} />}
        <input name="search" defaultValue={search} placeholder="Nombre, subdominio o email del dueño…" aria-label="Buscar tiendas" className={`${inputClass} max-w-sm`} />
        <button type="submit" className={secondaryButtonClass}>Buscar</button>
      </form>

      {stores.length === 0 ? (
        <p className="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-center text-zinc-500">
          {search || status ? 'No hay tiendas con ese filtro.' : 'Todavía no hay tiendas.'}
        </p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
          <table className="w-full min-w-[48rem] text-sm">
            <thead className="border-b border-zinc-200 text-left text-xs text-zinc-500">
              <tr>
                <th className="p-3 font-medium">Tienda</th>
                <th className="p-3 font-medium">Estado</th>
                <th className="p-3 font-medium">Plan</th>
                <th className="p-3 font-medium">Dueño</th>
                <th className="p-3 font-medium">Productos</th>
                <th className="p-3 font-medium">Usuarios</th>
                <th className="p-3 font-medium">Alta</th>
              </tr>
            </thead>
            <tbody>
              {stores.map((store) => (
                <tr key={store.id} className="border-b border-zinc-100 last:border-0">
                  <td className="p-3">
                    <Link href={`/platform/stores/${store.id}`} className="font-medium hover:underline">{store.name}</Link>
                    <p className="font-mono text-xs text-zinc-500">
                      {store.status === 'deleted' ? store.slug : (
                        <a href={storefrontUrl(store.slug)} target="_blank" rel="noreferrer" className="inline-flex min-h-6 items-center hover:underline">
                          {store.slug}<span className="sr-only"> (abrir la tienda en otra pestaña)</span>
                        </a>
                      )}
                    </p>
                  </td>
                  <td className="p-3"><StoreStatusBadge status={store.status} /></td>
                  <td className="p-3 text-zinc-700">
                    {store.plan.name}
                    {store.plan.billing_cycle && <span className="text-xs text-zinc-500"> · {store.plan.billing_cycle === 'annual' ? 'anual' : 'mensual'}</span>}
                  </td>
                  <td className="p-3 text-zinc-700">{store.owners.map((owner) => owner.email).join(', ') || '—'}</td>
                  <td className="p-3 text-zinc-700">{store.products_count}</td>
                  <td className="p-3 text-zinc-700">{store.users_count}</td>
                  <td className="p-3 text-zinc-700">{store.created_at ? new Date(store.created_at).toLocaleDateString('es-AR') : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {meta.last_page > 1 && (
        <nav aria-label="Paginación" className="mt-4 flex items-center justify-center gap-4 text-sm">
          {meta.current_page > 1 && <Link href={link({ page: meta.current_page - 1 })}>← Anterior</Link>}
          <span>Página {meta.current_page} de {meta.last_page} · {meta.total} tiendas</span>
          {meta.current_page < meta.last_page && <Link href={link({ page: meta.current_page + 1 })}>Siguiente →</Link>}
        </nav>
      )}
    </>
  )
}
