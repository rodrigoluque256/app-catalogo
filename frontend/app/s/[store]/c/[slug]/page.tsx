import type { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { CategoryNav } from '@/components/CategoryNav'
import { ProductGrid } from '@/components/ProductGrid'
import { getCategories, getProducts, getStore } from '@/lib/api'
import { excerpt, storeOpenGraph } from '@/lib/seo'

type Props = { params: Promise<{ store: string; slug: string }> }

// Vacío a propósito: no se prerenderiza nada en el build; cada página se genera en la primera visita y se
// cachea (ISR) hasta que el backend pida la revalidación por tag (ver app/api/revalidate).
export function generateStaticParams() {
  return []
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { store: storeSlug, slug } = await params
  const [store, categories] = await Promise.all([getStore(storeSlug), getCategories(storeSlug)])
  const category = categories.find((candidate) => candidate.slug === slug)

  if (!store || !category) return {}

  const description = excerpt(category.description)
  const url = `/c/${category.slug}`

  return {
    title: category.name,
    description,
    alternates: { canonical: url },
    openGraph: { ...storeOpenGraph(store), title: category.name, description, url },
  }
}

export default async function CategoryPage({ params }: Props) {
  const { store: storeSlug, slug } = await params
  const [store, categories] = await Promise.all([getStore(storeSlug), getCategories(storeSlug)])
  const category = categories.find((candidate) => candidate.slug === slug)

  if (!store || !category) notFound()

  const children = categories.filter((candidate) => candidate.parent_id === category.id)
  const products = await getProducts(storeSlug, { categoryId: category.id, perPage: 60 })

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="text-2xl font-semibold">{category.name}</h1>
        {category.description && <p className="mt-1 text-zinc-600">{category.description}</p>}
      </div>
      {children.length > 0 && <CategoryNav categories={children.map((child) => ({ ...child, parent_id: null }))} />}
      <ProductGrid products={products.data} store={store} />
    </div>
  )
}
