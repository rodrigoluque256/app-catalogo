import type { Metadata } from 'next'
import Link from 'next/link'
import { deleteProductAction } from '@/app/admin/actions'
import { DeleteButton } from '@/components/admin/DeleteButton'
import { buttonClass, inputClass, PageHeader, secondaryButtonClass, StatusBadge } from '@/components/admin/ui'
import { adminRequest } from '@/lib/admin/api'
import type { AdminProduct, Paginated } from '@/lib/admin/types'
import { formatMoney } from '@/lib/money'
import { requireSession } from '@/lib/admin/api'

export const metadata: Metadata = { title: 'Productos' }

type Props = { searchParams: Promise<{ search?: string; status?: string; page?: string }> }

export default async function ProductsPage({ searchParams }: Props) {
  const session = await requireSession()
  const { search = '', status = '', page = '1' } = await searchParams

  const query = new URLSearchParams({ per_page: '20', page: String(Math.max(1, Number(page) || 1)), sort: '-created_at' })
  if (search) query.set('search', search)
  if (['draft', 'active', 'archived'].includes(status)) query.set('status', status)

  const result = await adminRequest<Paginated<AdminProduct>>(`admin/products?${query}`, { envelope: true })

  if (!result.ok) return <p role="alert" className="text-red-600">{result.message}</p>

  const { data: products, meta } = result.data
  const link = (target: number) => `/admin/products?${new URLSearchParams({ ...(search && { search }), ...(status && { status }), page: String(target) })}`

  return (
    <>
      <PageHeader title="Productos">
        <Link href="/admin/products/new" className={buttonClass}>＋ Nuevo producto</Link>
      </PageHeader>

      <form className="mb-4 flex flex-wrap gap-2" role="search">
        <input name="search" defaultValue={search} placeholder="Buscar por nombre…" aria-label="Buscar productos" className={`${inputClass} max-w-xs`} />
        <select name="status" defaultValue={status} aria-label="Estado" className={`${inputClass} max-w-40`}>
          <option value="">Todos</option>
          <option value="active">Activos</option>
          <option value="draft">Borradores</option>
          <option value="archived">Archivados</option>
        </select>
        <button type="submit" className={secondaryButtonClass}>Filtrar</button>
      </form>

      {products.length === 0 ? (
        <p className="rounded-lg border border-dashed border-zinc-300 bg-white p-8 text-center text-zinc-500">
          {search || status ? 'No hay productos con ese filtro.' : 'Todavía no cargaste productos.'}
        </p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
          <table className="w-full min-w-[40rem] text-sm">
            <thead className="border-b border-zinc-200 text-left text-xs text-zinc-500">
              <tr>
                <th className="p-3 font-medium">Producto</th>
                <th className="p-3 font-medium">Estado</th>
                <th className="p-3 font-medium">Precio</th>
                <th className="p-3 font-medium">Variantes</th>
                <th className="p-3" />
              </tr>
            </thead>
            <tbody>
              {products.map((product) => {
                const image = product.images.find((i) => i.is_primary) ?? product.images[0]

                return (
                  <tr key={product.id} className="border-b border-zinc-100 last:border-0">
                    <td className="p-3">
                      <div className="flex items-center gap-3">
                        {image ? (
                          // eslint-disable-next-line @next/next/no-img-element
                          <img src={image.url} alt="" className="h-10 w-10 rounded object-cover" />
                        ) : (
                          <div className="h-10 w-10 rounded bg-zinc-100" aria-hidden />
                        )}
                        <div>
                          <Link href={`/admin/products/${product.id}`} className="font-medium hover:underline">{product.name}</Link>
                          <p className="text-xs text-zinc-500">{product.category?.name ?? 'Sin categoría'}{product.is_featured && ' · ⭐ Destacado'}</p>
                        </div>
                      </div>
                    </td>
                    <td className="p-3"><StatusBadge status={product.status} /></td>
                    <td className="p-3">{formatMoney(product.price_amount, session.store.currency, session.store.locale)}</td>
                    <td className="p-3 text-zinc-600">{product.variants.length || '—'}</td>
                    <td className="space-x-3 p-3 text-right">
                      <Link href={`/admin/products/${product.id}`} className="text-sm underline">Editar</Link>
                      <DeleteButton confirmText={`¿Eliminar “${product.name}”? Dejará de verse en la tienda.`} action={deleteProductAction.bind(null, product.id)} />
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      {meta.last_page > 1 && (
        <nav aria-label="Paginación" className="mt-4 flex items-center justify-center gap-4 text-sm">
          {meta.current_page > 1 && <Link href={link(meta.current_page - 1)}>← Anterior</Link>}
          <span>Página {meta.current_page} de {meta.last_page} · {meta.total} productos</span>
          {meta.current_page < meta.last_page && <Link href={link(meta.current_page + 1)}>Siguiente →</Link>}
        </nav>
      )}
    </>
  )
}
