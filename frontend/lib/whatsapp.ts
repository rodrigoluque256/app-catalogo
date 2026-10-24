export type WhatsAppMessageInput = {
  template: string | null
  productName: string
  /** Opciones elegidas, ej. { talle: 'M', color: 'Negro' } */
  options?: Record<string, string>
  productUrl?: string
}

const DEFAULT_TEMPLATE = 'Hola! Me interesa {product}.'

export function buildWhatsAppMessage({ template, productName, options = {}, productUrl }: WhatsAppMessageInput): string {
  const chosen = Object.entries(options).map(([name, value]) => `${name}: ${value}`)
  const product = chosen.length > 0 ? `${productName} (${chosen.join(', ')})` : productName
  const base = (template?.trim() || DEFAULT_TEMPLATE).replaceAll('{product}', product)

  return productUrl ? `${base}\n${productUrl}` : base
}

/** wa.me exige el número en dígitos, sin "+". Devuelve null si no hay número. */
export function buildWhatsAppUrl(number: string | null, message: string): string | null {
  const digits = number?.replace(/\D/g, '') ?? ''

  return digits === '' ? null : `https://wa.me/${digits}?text=${encodeURIComponent(message)}`
}
