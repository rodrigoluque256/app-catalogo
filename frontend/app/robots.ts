import type { MetadataRoute } from 'next'
import { headers } from 'next/headers'
import { storeSlugFromHost } from '@/lib/host'
import { storeUrl } from '@/lib/seo'

// En la raíz porque el proxy no reescribe rutas con punto: la tienda se decide por el Host.
export default async function robots(): Promise<MetadataRoute.Robots> {
  const slug = storeSlugFromHost((await headers()).get('host'), process.env.ROOT_DOMAIN ?? 'localhost')

  if (slug === null) {
    // Dominio raíz: el panel no se indexa y /s/... es la copia de las tiendas sin subdominio.
    return { rules: { userAgent: '*', disallow: ['/admin', '/s/'] } }
  }

  return {
    // Las búsquedas son combinaciones infinitas; los productos ya están en el sitemap.
    rules: { userAgent: '*', allow: '/', disallow: '/buscar' },
    sitemap: storeUrl(slug, '/sitemap.xml'),
  }
}
