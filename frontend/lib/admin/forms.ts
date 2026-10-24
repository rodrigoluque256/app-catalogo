import type { AdminProduct, FieldErrors, ProductStatus } from './types'

/**
 * "18.500,50" | "18500.50" | "18500" | "$ 1.234" -> centavos. null si no es un monto válido.
 * Opera sobre el texto (no con floats) para no perder centavos.
 */
export function parseMoneyToCents(input: string): number | null {
  let text = input.replace(/[^\d.,]/g, '')

  if (text === '') return null

  const lastComma = text.lastIndexOf(',')
  const lastDot = text.lastIndexOf('.')
  let decimalSeparator: ',' | '.' | null = null

  if (lastComma !== -1 && lastDot !== -1) {
    decimalSeparator = lastComma > lastDot ? ',' : '.'
  } else if (lastComma !== -1) {
    decimalSeparator = ','
  } else if (lastDot !== -1) {
    // "1.234" son miles; "12.5" y "12.50" son decimales.
    decimalSeparator = /^\d{1,3}(\.\d{3})+$/.test(text) ? null : '.'
  }

  let integer = text
  let fraction = ''

  if (decimalSeparator !== null) {
    const index = text.lastIndexOf(decimalSeparator)
    integer = text.slice(0, index)
    fraction = text.slice(index + 1)
  }

  // Antes del decimal solo se admiten separadores de miles en grupos de 3: "1.234.567" sí, "1,2,3" no.
  if (integer !== '' && !/^(\d+|\d{1,3}([.,]\d{3})+)$/.test(integer)) return null

  integer = integer.replace(/[.,]/g, '') || '0'

  if (fraction.length > 2 || /\D/.test(fraction)) return null

  const cents = Number(integer) * 100 + Number(fraction.padEnd(2, '0') || '0')

  return Number.isSafeInteger(cents) ? cents : null
}

/** 1850050 -> "18500.50"; 1850000 -> "18500". */
export function centsToInput(cents: number | null): string {
  if (cents === null) return ''

  const whole = Math.floor(cents / 100)
  const rest = cents % 100

  return rest === 0 ? String(whole) : `${whole}.${String(rest).padStart(2, '0')}`
}

export type VariantForm = {
  key: string
  /** Un valor por cada nombre de opción, en el mismo orden. */
  values: string[]
  sku: string
  price: string
  stock: string
  isActive: boolean
}

export type ImageForm = { key: string; path: string; url: string; altText: string }

export type ProductForm = {
  name: string
  slug: string
  description: string
  categoryId: string
  sku: string
  price: string
  compareAt: string
  status: ProductStatus
  isFeatured: boolean
  optionNames: string[]
  variants: VariantForm[]
  /** La primera es la principal. */
  images: ImageForm[]
}

export const emptyProductForm = (): ProductForm => ({
  name: '', slug: '', description: '', categoryId: '', sku: '', price: '', compareAt: '',
  status: 'draft', isFeatured: false, optionNames: [], variants: [], images: [],
})

export function productToForm(product: AdminProduct): ProductForm {
  const optionNames: string[] = []

  for (const variant of product.variants) {
    for (const name of Object.keys(variant.options)) {
      if (!optionNames.includes(name)) optionNames.push(name)
    }
  }

  return {
    name: product.name,
    slug: product.slug,
    description: product.description ?? '',
    categoryId: product.category?.id ?? '',
    sku: product.sku ?? '',
    price: centsToInput(product.price_amount),
    compareAt: centsToInput(product.compare_at_amount),
    status: product.status,
    isFeatured: product.is_featured,
    optionNames,
    variants: [...product.variants]
      .sort((a, b) => a.position - b.position)
      .map((variant) => ({
        key: variant.id,
        values: optionNames.map((name) => variant.options[name] ?? ''),
        sku: variant.sku ?? '',
        price: centsToInput(variant.price_amount),
        stock: variant.stock === null ? '' : String(variant.stock),
        isActive: variant.is_active,
      })),
    images: [...product.images]
      .sort((a, b) => a.position - b.position)
      .sort((a, b) => Number(b.is_primary) - Number(a.is_primary))
      .map((image) => ({ key: image.id, path: image.path, url: image.url, altText: image.alt_text ?? '' })),
  }
}

export type ProductPayload = Record<string, unknown>

export type BuildResult = { ok: true; payload: ProductPayload } | { ok: false; errors: FieldErrors }

/** Formulario -> cuerpo de POST/PUT /admin/products. Valida lo que el navegador puede validar antes de viajar. */
export function buildProductPayload(form: ProductForm): BuildResult {
  const errors: FieldErrors = {}

  const price = parseMoneyToCents(form.price)
  if (price === null) errors.price_amount = ['Ingresá un precio válido.']

  const compareAt = form.compareAt.trim() === '' ? null : parseMoneyToCents(form.compareAt)
  if (form.compareAt.trim() !== '' && compareAt === null) errors.compare_at_amount = ['Ingresá un monto válido.']
  else if (compareAt !== null && price !== null && compareAt <= price) {
    errors.compare_at_amount = ['El precio anterior debe ser mayor al precio actual.']
  }

  const names = form.optionNames.map((name) => name.trim())
  if (new Set(names.map((name) => name.toLowerCase())).size !== names.length || names.some((name) => name === '')) {
    errors.variants = ['Cada opción necesita un nombre distinto (ej. talle, color).']
  }

  const variants = form.variants.map((variant, index) => {
    const options: Record<string, string> = {}
    names.forEach((name, i) => {
      const value = (variant.values[i] ?? '').trim()
      if (name !== '' && value !== '') options[name] = value
    })

    if (Object.keys(options).length === 0) errors[`variants.${index}.options`] = ['Completá al menos una opción.']

    const variantPrice = variant.price.trim() === '' ? null : parseMoneyToCents(variant.price)
    if (variant.price.trim() !== '' && variantPrice === null) errors[`variants.${index}.price_amount`] = ['Monto inválido.']

    const stock = variant.stock.trim() === '' ? null : Number(variant.stock)
    if (stock !== null && (!Number.isInteger(stock) || stock < 0)) errors[`variants.${index}.stock`] = ['Stock inválido.']

    return {
      options,
      sku: variant.sku.trim() || null,
      price_amount: variantPrice,
      stock,
      is_active: variant.isActive,
    }
  })

  if (Object.keys(errors).length > 0) return { ok: false, errors }

  return {
    ok: true,
    payload: {
      name: form.name.trim(),
      slug: form.slug.trim() || null,
      description: form.description.trim() || null,
      category_id: form.categoryId || null,
      sku: form.sku.trim() || null,
      price_amount: price,
      compare_at_amount: compareAt,
      status: form.status,
      is_featured: form.isFeatured,
      variants,
      images: form.images.map((image, index) => ({
        path: image.path,
        alt_text: image.altText.trim() || null,
        is_primary: index === 0,
      })),
    },
  }
}

/** Laravel responde `errors` con claves "variants.0.sku"; devuelve el primer mensaje de una clave. */
export function firstError(errors: FieldErrors, key: string): string | undefined {
  return errors[key]?.[0]
}

let keyCounter = 0

/** Clave única para listas de React. No usa crypto.randomUUID: no existe en páginas http:// que no sean localhost. */
export function newKey(): string {
  keyCounter += 1
  return `k${Date.now().toString(36)}${keyCounter}${Math.random().toString(36).slice(2, 6)}`
}
