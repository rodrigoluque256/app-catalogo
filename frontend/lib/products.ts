import type { Product, ProductImage, ProductVariant } from './types'

const byPosition = <T extends { position: number }>(a: T, b: T) => a.position - b.position

export function primaryImage(product: Product): ProductImage | null {
  return product.images.find((image) => image.is_primary) ?? [...product.images].sort(byPosition)[0] ?? null
}

export function sortedImages(product: Product): ProductImage[] {
  const primary = primaryImage(product)

  return [...product.images].sort(byPosition).sort((a, b) => Number(b === primary) - Number(a === primary))
}

export function effectivePrice(product: Product, variant: ProductVariant | null): number {
  return variant?.price_amount ?? product.price_amount
}

/** "Desde $X" cuando alguna variante es más barata que el precio base. */
export function listingPrice(product: Product): { amount: number; isFrom: boolean } {
  const prices = [product.price_amount, ...product.variants.map((v) => v.price_amount ?? product.price_amount)]
  const min = Math.min(...prices)

  return { amount: min, isFrom: prices.some((price) => price !== min) }
}

export function isSoldOut(product: Product): boolean {
  return product.variants.length > 0 && product.variants.every((variant) => !variant.in_stock)
}

/** { talle: ['S','M'], color: ['Negro'] } preservando el orden de aparición. */
export function optionGroups(variants: ProductVariant[]): Record<string, string[]> {
  const groups: Record<string, string[]> = {}

  for (const variant of [...variants].sort(byPosition)) {
    for (const [name, value] of Object.entries(variant.options)) {
      const values = (groups[name] ??= [])
      if (!values.includes(value)) values.push(value)
    }
  }

  return groups
}

export function findVariant(variants: ProductVariant[], selection: Record<string, string>): ProductVariant | null {
  return (
    variants.find((variant) => {
      const entries = Object.entries(variant.options)
      return entries.length === Object.keys(selection).length && entries.every(([name, value]) => selection[name] === value)
    }) ?? null
  )
}
