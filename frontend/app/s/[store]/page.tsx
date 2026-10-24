import { notFound } from 'next/navigation'
import { ProductGrid } from '@/components/ProductGrid'
import { getProducts, getStore } from '@/lib/api'

type Props = { params: Promise<{ store: string }> }

// Vacío a propósito: no se prerenderiza nada en el build; cada página se genera en la primera visita y se
// cachea (ISR) hasta que el backend pida la revalidación por tag (ver app/api/revalidate).
export function generateStaticParams() {
  return []
}

const PAGE_SIZE = 60

export default async function StoreHome({ params }: Props) {
  const { store: slug } = await params
  const [store, featured, all] = await Promise.all([
    getStore(slug),
    getProducts(slug, { featured: true, perPage: 8 }),
    getProducts(slug, { perPage: PAGE_SIZE }),
  ])

  if (!store) notFound()

  const featuredIds = new Set(featured.data.map((product) => product.id))
  const rest = all.data.filter((product) => !featuredIds.has(product.id))

  return (
    <div className="flex flex-col gap-10">
      {featured.data.length > 0 && (
        <section aria-labelledby="destacados">
          <h2 id="destacados" className="mb-3 text-lg font-semibold">
            Destacados
          </h2>
          <ProductGrid products={featured.data} store={store} />
        </section>
      )}

      <section aria-labelledby="todos">
        <h2 id="todos" className="mb-3 text-lg font-semibold">
          {featured.data.length > 0 ? 'Más productos' : 'Productos'}
        </h2>
        <ProductGrid products={rest} store={store} />
        {all.meta.total > PAGE_SIZE && (
          <p className="mt-6 text-center text-sm text-zinc-500">
            Mostrando {PAGE_SIZE} de {all.meta.total}. Usá el buscador o las categorías para ver el resto.
          </p>
        )}
      </section>
    </div>
  )
}
