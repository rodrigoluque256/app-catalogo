import type { MetadataRoute } from 'next'
import { headers } from 'next/headers'
import { getCategories, getProducts, getStore } from '@/lib/api'
import { storeSlugFromHost } from '@/lib/host'
import { storeUrl } from '@/lib/seo'

// La API devuelve hasta 60 por página; el tope evita un bucle largo si una tienda crece de más.
const PER_PAGE = 60
const MAX_PAGES = 50

// En la raíz porque el proxy no reescribe rutas con punto: la tienda se decide por el Host.
// Los fetch siguen cacheados por tag, así que la revalidación del backend también refresca el sitemap.
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const slug = storeSlugFromHost((await headers()).get('host'), process.env.ROOT_DOMAIN ?? 'localhost')

  if (slug === null) return []

  const [store, categories, firstPage] = await Promise.all([
    getStore(slug),
    getCategories(slug),
    getProducts(slug, { page: 1, perPage: PER_PAGE }),
  ])

  if (!store) return []

  const lastPage = Math.min(firstPage.meta.last_page, MAX_PAGES)
  const rest = await Promise.all(
    Array.from({ length: lastPage - 1 }, (_, index) => getProducts(slug, { page: index + 2, perPage: PER_PAGE })),
  )
  const products = [firstPage, ...rest].flatMap((page) => page.data)

  return [
    { url: storeUrl(slug, '/') },
    ...categories.filter((category) => category.is_active).map((category) => ({ url: storeUrl(slug, `/c/${category.slug}`) })),
    ...products.map((product) => ({ url: storeUrl(slug, `/p/${product.slug}`) })),
  ]
}
