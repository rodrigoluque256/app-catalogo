// Contrato con el backend (App\Domain\Shared\Events\StorefrontTags): tienen que ser idénticos.
export const tags = {
  store: (store: string) => `store:${store}`,
  categories: (store: string) => `store:${store}:categories`,
  products: (store: string) => `store:${store}:products`,
  product: (store: string, product: string) => `store:${store}:product:${product}`,
}
