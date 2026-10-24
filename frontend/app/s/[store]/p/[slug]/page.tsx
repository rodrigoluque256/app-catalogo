import type { Metadata } from 'next'
import Link from 'next/link'
import { notFound } from 'next/navigation'
import { ProductGallery } from '@/components/ProductGallery'
import { ProductPurchase } from '@/components/ProductPurchase'
import { getProduct, getStore } from '@/lib/api'
import { sortedImages } from '@/lib/products'

type Props = { params: Promise<{ store: string; slug: string }> }

// Vacío a propósito: no se prerenderiza nada en el build; cada página se genera en la primera visita y se
// cachea (ISR) hasta que el backend pida la revalidación por tag (ver app/api/revalidate).
export function generateStaticParams() {
  return []
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { store, slug } = await params
  const product = await getProduct(store, slug)

  if (!product) return {}

  const image = sortedImages(product)[0]
  const description = product.description?.slice(0, 160)

  return {
    title: product.name,
    description,
    openGraph: { title: product.name, description, images: image ? [image.url] : undefined },
  }
}

export default async function ProductPage({ params }: Props) {
  const { store: storeSlug, slug } = await params
  const [store, product] = await Promise.all([getStore(storeSlug), getProduct(storeSlug, slug)])

  if (!store || !product) notFound()

  return (
    <article className="grid gap-8 md:grid-cols-2">
      <ProductGallery images={sortedImages(product)} name={product.name} />

      <div className="flex flex-col gap-5">
        <div>
          {product.category && (
            <Link href={`/c/${product.category.slug}`} className="text-sm text-accent">
              {product.category.name}
            </Link>
          )}
          <h1 className="mt-1 text-2xl font-semibold">{product.name}</h1>
        </div>

        <ProductPurchase
          product={product}
          currency={store.currency}
          locale={store.locale}
          whatsappNumber={store.whatsapp.number}
          whatsappTemplate={store.whatsapp.message_template}
        />

        {product.description && (
          <section aria-labelledby="descripcion">
            <h2 id="descripcion" className="mb-1 font-medium">
              Descripción
            </h2>
            <p className="whitespace-pre-line text-zinc-700">{product.description}</p>
          </section>
        )}
      </div>
    </article>
  )
}
