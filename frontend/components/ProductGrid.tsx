import { ProductCard } from './ProductCard'
import type { Product, Store } from '@/lib/types'

export function ProductGrid({ products, store }: { products: Product[]; store: Store }) {
  if (products.length === 0) {
    return <p className="py-12 text-center text-zinc-500">No hay productos para mostrar.</p>
  }

  return (
    <ul className="grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 lg:grid-cols-4">
      {products.map((product) => (
        <li key={product.id}>
          <ProductCard product={product} store={store} />
        </li>
      ))}
    </ul>
  )
}
