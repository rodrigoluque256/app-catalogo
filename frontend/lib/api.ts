import 'server-only'
import type { Category, Paginated, Plan, Product, Store } from './types'
import { tags } from './tags'

const API_URL = process.env.API_URL ?? 'http://localhost:8000'

/** Si ningún webhook de revalidación llega, el contenido se refresca igual cada hora. */
const FALLBACK_REVALIDATE_SECONDS = 3600

type Options = { tags: string[]; fresh?: boolean }

async function get<T>(store: string, path: string, { tags, fresh = false }: Options): Promise<T | null> {
  const response = await fetch(`${API_URL}/api/v1/storefront/${path}`, {
    headers: { Accept: 'application/json', 'X-Store-Slug': store },
    ...(fresh ? { cache: 'no-store' } : { cache: 'force-cache', next: { tags, revalidate: FALLBACK_REVALIDATE_SECONDS } }),
  })

  if (response.status === 404) return null
  if (!response.ok) throw new Error(`API ${response.status} en ${path}`)

  return (await response.json()) as T
}

export async function getStore(store: string): Promise<Store | null> {
  const body = await get<{ data: Store }>(store, 'store', { tags: [tags.store(store)] })

  return body?.data ?? null
}

export async function getCategories(store: string): Promise<Category[]> {
  const body = await get<{ data: Category[] }>(store, 'categories', { tags: [tags.categories(store)] })

  return body?.data ?? []
}

export type ProductQuery = {
  categoryId?: string
  featured?: boolean
  search?: string
  page?: number
  perPage?: number
  sort?: string
}

export async function getProducts(store: string, query: ProductQuery = {}): Promise<Paginated<Product>> {
  const params = new URLSearchParams()
  if (query.categoryId) params.set('category_id', query.categoryId)
  if (query.featured) params.set('featured', '1')
  if (query.search) params.set('search', query.search)
  if (query.page) params.set('page', String(query.page))
  if (query.sort) params.set('sort', query.sort)
  params.set('per_page', String(query.perPage ?? 24))

  // Las búsquedas son infinitas combinaciones: no se cachean.
  const body = await get<Paginated<Product>>(store, `products?${params}`, {
    tags: [tags.products(store)],
    fresh: Boolean(query.search),
  })

  return body ?? { data: [], meta: { current_page: 1, last_page: 1, per_page: 24, total: 0 } }
}

export async function getProduct(store: string, slug: string): Promise<Product | null> {
  const body = await get<{ data: Product }>(store, `products/${encodeURIComponent(slug)}`, {
    tags: [tags.product(store, slug)],
  })

  return body?.data ?? null
}

/** Planes públicos (landing, registro, "Mi plan"). No dependen de una tienda: cache propio por hora. */
export async function getPlans(): Promise<Plan[]> {
  const response = await fetch(`${API_URL}/api/v1/plans`, {
    headers: { Accept: 'application/json' },
    cache: 'force-cache',
    next: { tags: ['plans'], revalidate: FALLBACK_REVALIDATE_SECONDS },
  })

  if (!response.ok) return []

  return ((await response.json()) as { data: Plan[] }).data
}
