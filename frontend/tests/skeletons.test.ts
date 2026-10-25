import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { describe, expect, it } from 'vitest'
import { LoadingRegion, ProductGridSkeleton, Skeleton } from '@/components/Skeleton'
import ProductLoading from '@/app/s/[store]/p/[slug]/loading'
import ProductsAdminLoading from '@/app/admin/(panel)/products/loading'

describe('skeletons', () => {
  it('los bloques son decorativos y respetan prefers-reduced-motion', () => {
    const html = renderToStaticMarkup(createElement(Skeleton, { className: 'h-4' }))

    expect(html).toContain('aria-hidden="true"')
    expect(html).toContain('motion-reduce:animate-none')
  })

  it('LoadingRegion anuncia la carga a lectores de pantalla', () => {
    const html = renderToStaticMarkup(createElement(LoadingRegion, { label: 'Cargando productos…', children: null }))

    expect(html).toContain('role="status"')
    expect(html).toContain('aria-busy="true"')
    expect(html).toMatch(/<span class="sr-only">Cargando productos…<\/span>/)
  })

  it('ProductGridSkeleton dibuja la cantidad pedida de tarjetas', () => {
    const html = renderToStaticMarkup(createElement(ProductGridSkeleton, { count: 5 }))

    expect(html.match(/<li/g)).toHaveLength(5)
  })

  it.each([
    ['producto (tienda)', ProductLoading, 'Cargando producto…'],
    ['productos (panel)', ProductsAdminLoading, 'Cargando productos…'],
  ])('loading de %s es una región de estado con etiqueta', (_, Loading, label) => {
    const html = renderToStaticMarkup(createElement(Loading))

    expect(html).toContain('role="status"')
    expect(html).toContain(label)
  })
})
