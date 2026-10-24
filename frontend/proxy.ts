import { NextResponse, type NextRequest } from 'next/server'
import { storeSlugFromHost } from '@/lib/host'

const ROOT_DOMAIN = process.env.ROOT_DOMAIN ?? 'localhost'

/**
 * tienda.dominio.com/p/remera  ->  /s/tienda/p/remera (reescritura interna, la URL del navegador no cambia).
 * Next cachea (ISR) por la ruta reescrita, así cada tienda tiene sus propias páginas.
 */
export function proxy(request: NextRequest) {
  const slug = storeSlugFromHost(request.headers.get('host'), ROOT_DOMAIN)

  if (slug === null) return NextResponse.next()

  const url = request.nextUrl.clone()
  url.pathname = `/s/${slug}${url.pathname === '/' ? '' : url.pathname}`

  return NextResponse.rewrite(url)
}

export const config = {
  // Sin /api (revalidación), assets de Next ni archivos estáticos.
  matcher: ['/((?!api|_next|.*\\..*).*)'],
}
