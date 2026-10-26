// Respuestas de /api/v1/admin y /api/v1/auth (ver backend: ProductResource, CategoryResource, StoreResource).
import type { Category, Paginated, Plan, Store } from '@/lib/types'

export type { Category, Paginated, Plan, Store }

export type ProductStatus = 'draft' | 'active' | 'archived'

export type AdminVariant = {
  id: string
  sku: string | null
  options: Record<string, string>
  price_amount: number | null
  /** null = sin control de stock */
  stock: number | null
  in_stock: boolean
  is_active: boolean
  position: number
}

export type AdminImage = {
  id: string
  url: string
  path: string
  alt_text: string | null
  width: number | null
  height: number | null
  position: number
  is_primary: boolean
}

export type AdminProduct = {
  id: string
  name: string
  slug: string
  description: string | null
  sku: string | null
  price_amount: number
  compare_at_amount: number | null
  status: ProductStatus
  is_featured: boolean
  category: Category | null
  variants: AdminVariant[]
  images: AdminImage[]
  updated_at: string | null
}

export type Session = {
  user: { id: number; name: string; email: string; must_change_password: boolean }
  store: Store
  roles: string[]
  permissions: string[]
  plan: Plan
}

export type PresignedUpload = {
  path: string
  upload_url: string
  headers: Record<string, string>
  public_url: string
}

/** Errores de validación de Laravel: { "variants.0.sku": ["…"] } */
export type FieldErrors = Record<string, string[]>

export type ActionResult = { ok: true } | { ok: false; message: string; errors: FieldErrors }

export type UserRole = 'owner' | 'editor'

export type AdminUser = {
  id: number
  name: string
  email: string
  role: UserRole | null
  must_change_password: boolean
  invitation_pending: boolean
  is_current_user: boolean
  created_at: string | null
}

/** Alta de usuario o envío de acceso: se avisa a quién le llegó el email (ya no hay contraseña temporal). */
export type AccessResult =
  | { ok: true; email: string; sent: 'invitation' | 'reset' }
  | { ok: false; message: string; errors: FieldErrors }

export type StorePlan = {
  plan: Plan
  subscription: { status: 'active' | 'past_due' | 'canceled'; billing_cycle: 'monthly' | 'annual' | null; current_period_end: string | null }
  usage: { products: number; users: number }
}
