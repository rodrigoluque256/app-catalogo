// Forma de las respuestas de /api/v1/storefront (ver backend: StoreResource, StorefrontProductResource, CategoryResource).

export type Store = {
  id: string
  name: string
  slug: string
  custom_domain: string | null
  logo_url: string | null
  theme: { primary_color: string; accent_color: string }
  whatsapp: { number: string | null; message_template: string | null }
  currency: string
  locale: string
}

export type Category = {
  id: string
  parent_id: string | null
  name: string
  slug: string
  description: string | null
  position: number
  is_active: boolean
}

export type ProductVariant = {
  id: string
  options: Record<string, string>
  /** Centavos. null = hereda el precio del producto. */
  price_amount: number | null
  in_stock: boolean
  position: number
}

export type ProductImage = {
  id: string
  url: string
  alt_text: string | null
  width: number | null
  height: number | null
  position: number
  is_primary: boolean
  variant_id: string | null
}

export type Product = {
  id: string
  name: string
  slug: string
  description: string | null
  /** Centavos. */
  price_amount: number
  compare_at_amount: number | null
  is_featured: boolean
  category: Category | null
  variants: ProductVariant[]
  images: ProductImage[]
}

export type Paginated<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

/** Plan público (GET /api/v1/plans). Precios en centavos ARS; `products` null = sin límite. */
export type Plan = {
  code: string
  name: string
  monthly_price: number
  annual_price: number
  limits: { products: number | null; images_per_product: number; users: number }
}
