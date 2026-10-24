import type { Metadata } from 'next'
import Link from 'next/link'
import { notFound } from 'next/navigation'
import { ProductGrid } from '@/components/ProductGrid'
import { getProducts, getStore } from '@/lib/api'

type Props = {
  params: Promise<{ store: string }>
  searchParams: Promise<{ q?: string; page?: string }>
}

export const metadata: Metadata = { title: 'Buscar', robots: { index: false } }

export default async function SearchPage({ params, searchParams }: Props) {
  const { store: slug } = await params
  const { q = '', page: rawPage } = await searchParams
  const query = q.trim().slice(0, 100)
  const page = Math.max(1, Number.parseInt(rawPage ?? '1', 10) || 1)

  const store = await getStore(slug)
  if (!store) notFound()

  const products = query ? await getProducts(slug, { search: query, page, perPage: 24 }) : null
  const href = (target: number) => `/buscar?q=${encodeURIComponent(query)}&page=${target}`

  return (
    <div className="flex flex-col gap-5">
      <h1 className="text-2xl font-semibold">{query ? `Resultados para “${query}”` : 'Buscar productos'}</h1>

      {products && (
        <>
          <p className="text-sm text-zinc-500">{products.meta.total} resultado(s)</p>
          <ProductGrid products={products.data} store={store} />
          {products.meta.last_page > 1 && (
            <nav aria-label="Paginación" className="flex items-center justify-center gap-4 text-sm">
              {page > 1 && <Link href={href(page - 1)}>← Anterior</Link>}
              <span>
                Página {products.meta.current_page} de {products.meta.last_page}
              </span>
              {page < products.meta.last_page && <Link href={href(page + 1)}>Siguiente →</Link>}
            </nav>
          )}
        </>
      )}
    </div>
  )
}
