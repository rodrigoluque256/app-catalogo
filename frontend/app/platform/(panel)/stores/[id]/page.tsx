import type { Metadata } from 'next'
import Link from 'next/link'
import { notFound } from 'next/navigation'
import { PageHeader } from '@/components/admin/ui'
import { StoreActions } from '@/components/platform/StoreActions'
import { StoreStatusBadge } from '@/components/platform/StoreStatusBadge'
import { storefrontUrl } from '@/lib/admin/api'
import { platformRequest } from '@/lib/platform/api'
import type { PlatformStore } from '@/lib/platform/types'

type Props = { params: Promise<{ id: string }> }

async function getStore(id: string): Promise<PlatformStore | null> {
  const result = await platformRequest<PlatformStore>(`stores/${encodeURIComponent(id)}`)

  return result.ok ? result.data : null
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const store = await getStore((await params).id)

  return { title: store?.name ?? 'Tienda' }
}

export default async function StorePage({ params }: Props) {
  const store = await getStore((await params).id)

  if (!store) notFound()

  const date = (value: string | null) => (value ? new Date(value).toLocaleString('es-AR') : '—')

  return (
    <>
      <p className="mb-2 text-sm"><Link href="/platform/stores" className="underline">← Tiendas</Link></p>
      <PageHeader title={store.name}>
        <StoreStatusBadge status={store.status} />
      </PageHeader>

      <div className="grid max-w-4xl gap-4 lg:grid-cols-2">
        <section aria-labelledby="datos" className="rounded-lg border border-zinc-200 bg-white p-5 text-sm">
          <h2 id="datos" className="mb-3 font-semibold">Datos</h2>
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
            <dt className="text-zinc-500">Subdominio</dt>
            <dd className="font-mono">
              {store.status === 'active' ? (
                <a href={storefrontUrl(store.slug)} target="_blank" rel="noreferrer" className="underline">
                  {store.slug}<span className="sr-only"> (abrir la tienda en otra pestaña)</span>
                </a>
              ) : store.slug}
            </dd>
            <dt className="text-zinc-500">Dueño</dt>
            <dd>{store.owners.length ? store.owners.map((owner) => `${owner.name} (${owner.email})`).join(', ') : '—'}</dd>
            <dt className="text-zinc-500">Productos</dt>
            <dd>{store.products_count}</dd>
            <dt className="text-zinc-500">Usuarios</dt>
            <dd>{store.users_count}</dd>
            <dt className="text-zinc-500">Alta</dt>
            <dd>{date(store.created_at)}</dd>
            {store.deleted_at && (
              <>
                <dt className="text-zinc-500">Borrada</dt>
                <dd>{date(store.deleted_at)}</dd>
              </>
            )}
          </dl>
        </section>

        <div className="flex flex-col gap-4">
          <StoreActions store={store} />
        </div>
      </div>
    </>
  )
}
