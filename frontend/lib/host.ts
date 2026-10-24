/**
 * "demo.localhost:3000" con rootDomain "localhost" -> "demo".
 * Devuelve null para el dominio raíz, "www", hosts de otro dominio o subdominios anidados.
 */
export function storeSlugFromHost(host: string | null, rootDomain: string): string | null {
  if (!host) return null

  const hostname = host.split(':')[0]?.toLowerCase() ?? ''
  const root = rootDomain.toLowerCase()
  const suffix = `.${root}`

  if (!hostname.endsWith(suffix)) return null

  const label = hostname.slice(0, -suffix.length)

  if (label === '' || label === 'www' || label.includes('.')) return null

  return /^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/.test(label) ? label : null
}
