/** "Almacén Don José" -> "almacen-don-jose". Mismo formato que el subdominio de una tienda (label DNS, ≤ 63). */
export function slugify(text: string, max = 63): string {
  return text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, max)
    .replace(/-+$/, '')
}
