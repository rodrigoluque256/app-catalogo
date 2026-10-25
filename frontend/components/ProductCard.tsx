import Image from 'next/image'
import Link from 'next/link'
import { formatMoney } from '@/lib/money'
import { isSoldOut, listingPrice, primaryImage } from '@/lib/products'
import type { Product, Store } from '@/lib/types'

export function ProductCard({ product, store }: { product: Product; store: Store }) {
  const image = primaryImage(product)
  const { amount, isFrom } = listingPrice(product)
  const soldOut = isSoldOut(product)
  const onSale = product.compare_at_amount !== null && product.compare_at_amount > amount

  return (
    <Link href={`/p/${product.slug}`} className="group flex flex-col gap-2">
      <div className="relative aspect-square overflow-hidden rounded-lg bg-zinc-100">
        {image ? (
          <Image
            src={image.url}
            alt={image.alt_text ?? product.name}
            fill
            sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-sm text-zinc-600">Sin imagen</div>
        )}
        {soldOut && (
          <span className="absolute left-2 top-2 rounded bg-zinc-900 px-2 py-0.5 text-xs font-medium text-white">Agotado</span>
        )}
        {!soldOut && onSale && (
          <span className="absolute left-2 top-2 rounded bg-accent px-2 py-0.5 text-xs font-medium text-accent-fg">Oferta</span>
        )}
      </div>
      <div>
        <h3 className="line-clamp-2 text-sm font-medium">{product.name}</h3>
        <p className="mt-0.5 flex items-baseline gap-2 text-sm">
          <span className="font-semibold">
            {isFrom && <span className="mr-1 font-normal text-zinc-500">Desde</span>}
            {formatMoney(amount, store.currency, store.locale)}
          </span>
          {onSale && (
            <span className="text-xs text-zinc-500 line-through">
              {formatMoney(product.compare_at_amount!, store.currency, store.locale)}
            </span>
          )}
        </p>
      </div>
    </Link>
  )
}
